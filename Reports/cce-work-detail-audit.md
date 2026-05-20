# CCE Work Detail Module - Security & Architecture Audit

**Audit Reliability: 90%**
*Critical missing files:* None. Full controllers, models, policies, and frontend components were provided. 
*Moderate missing files:* Route definitions (inferred from `api.php` grep/routes.txt) and database migrations (inferred from models). 

---

## 1. Module Overview
`Confidence: High — Full controllers and frontend provided`

The CCE (Continuous and Comprehensive Evaluation) Work Detail module handles the grading and tracking of student assignments. It allows teachers and principals to view a specific assignment (CCE Work), see all student submissions, and evaluate them either individually or in bulk. 

**Primary Workflows:**
1. **Teacher/Principal** views a specific work and its submissions.
2. **Teacher/Principal** evaluates a single submission (assigning marks and feedback).
3. **Teacher/Principal** bulk-evaluates multiple submissions simultaneously.
4. **Student** submits work (uploading files).

---

## 2. Module Boundary Analysis
`Confidence: High — Clear separation of concerns`

- **Frontend:** `CCEWorkDetailPage.tsx` (React/Lucide-react/Tailwind)
- **Backend Controllers:** `CCEWorkController.php`, `CCESubmissionController.php`
- **Models:** `CCEWork.php`, `CCESubmission.php`, `Subject.php`
- **Policies:** `CCEWorkPolicy.php`

---

## 3. Functional Behavior
`Confidence: High — Controller logic provided`

| Purpose | Trigger | Allowed Roles | Validation | Security Implications |
|---|---|---|---|---|
| View Work | `GET /cce/works/{id}` | Teacher (owner), Principal | None | Must restrict access to the work's owner |
| Evaluate Single | `POST /cce/submissions/{id}/evaluate` | Teacher (owner), Principal | marks <= max_marks | Must verify ownership of the parent work |
| Evaluate Bulk | `POST /cce/submissions/bulk-evaluate` | Teacher (owner), Principal | marks <= max_marks | **Critical Risk**: Must verify ownership for *every* submission ID provided |
| Submit Work | `POST /cce/submissions/{id}/submit` | Student (owner) | File mimes/size | Must restrict to the specific student |

---

## 4. Backend Architecture
`Confidence: High — Source code analyzed directly`

- **Controllers:** Controllers handle validation and business logic inline. No separate FormRequests or Services are used.
- **Data Fetching:** `CCEWorkController@show` uses eager loading (`subject.classRoom`, `submissions.student.user`) which avoids N+1 queries effectively.
- **Bulk Operations:** `CCESubmissionController@bulkEvaluate` performs a mass update.

**Request Lifecycle Diagram:**
```
Request → Auth Middleware → Controller Inline Validation → Gate::authorize() → Eloquent Query → JSON Response
```

---

## 5. Authorization & Permission Audit ⚠️
`Confidence: High — Policy and Controller logic analyzed`

**Policies:**
- `CCEWorkPolicy` correctly grants blanket access to Principals and users with `manage_cce` permission via the `before()` method.
- `view`, `update`, `delete`, and `evaluate` correctly verify if the authenticated teacher is the owner of the subject.

**Critical Weaknesses Found:**
1. **Missing Subject Ownership Check on Creation:** 
   In `CCEWorkController@store`, the gate `Gate::authorize('create', CCEWork::class)` only checks if the user is a teacher. It **does not** check if the `subject_id` passed in the request belongs to that teacher. 
   `⚠️ FALSE SECURITY PATTERN DETECTED`: A teacher can create assignments for another teacher's subject.

2. **Flawed Bulk Evaluation Authorization:**
   In `CCESubmissionController@bulkEvaluate`:
   ```php
   $submissions = CCESubmission::with('work')->whereIn('id', $validated['submission_ids'])->get();
   \Illuminate\Support\Facades\Gate::authorize('evaluate', $submissions->first()->work);
   ```
   The authorization ONLY checks the `work` of the **first** submission in the array. It assumes all submissions belong to the same work, but it never enforces this!
   `⚠️ FALSE SECURITY PATTERN DETECTED`: An attacker can pass `[valid_id, target_id_1, target_id_2]` and bypass the gate for the other records.

---

## 6. Security Audit ⚠️
`Confidence: High — Direct analysis of implementation`

### Risk Prioritization Matrix

#### Risk 1: Horizontal Privilege Escalation (IDOR) via Bulk Evaluate
| Field | Value |
|---|---|
| Category | Authorization / IDOR |
| Likelihood | 8 |
| Impact | 9 |
| Priority Score | 72 |
| Severity | High |
| Exploit Path | 1. Teacher grabs a valid submission ID they own.<br>2. Teacher guesses/brute-forces submission IDs belonging to other teachers' classes.<br>3. Teacher sends a POST to `/api/cce/submissions/bulk-evaluate` with `submission_ids: [ValidID, TargetID1, TargetID2]`.<br>4. Backend checks Auth on `ValidID` (Passes). Backend updates ALL IDs. |
| Current Mitigation | Gate checks the first element. |
| Weakness | No validation that all `submission_ids` belong to the same work, or that the user has permission to evaluate *all* of them. |
| Recommendation | Add validation: `$workId = $submissions->first()->work_id; if ($submissions->contains(fn($s) => $s->work_id !== $workId)) { abort(403); }` |

#### Risk 2: IDOR in Work Creation (Subject Spoofing)
| Field | Value |
|---|---|
| Category | Authorization / Mass Assignment |
| Likelihood | 7 |
| Impact | 7 |
| Priority Score | 49 |
| Severity | Medium |
| Exploit Path | 1. Teacher discovers a `subject_id` belonging to another teacher.<br>2. Teacher sends a POST to `/api/cce/works` with the targeted `subject_id`.<br>3. The system assigns the work to the victim's subject. |
| Current Mitigation | Gate checks if user is a teacher. |
| Weakness | Does not verify ownership of the specific `subject_id`. |
| Recommendation | Validate subject ownership in `CCEWorkController@store`: `abort_if($request->user()->isTeacher() && Subject::findOrFail($request->subject_id)->teacher_id !== $request->user()->id, 403);` |

---

## 7. Threat Model
`Confidence: High`

**Trust Boundaries:**
```
Frontend (Untrusted) → API (Trusted)
Teacher A (Untrusted) → Teacher B's Submissions (MUST verify via Query/Policy)
Student A (Untrusted) → Student B's Submissions (MUST verify ownership)
```
**High-Risk Data Flows:** The `bulk-evaluate` array input is highly dangerous because mass-updates (`whereIn('id', ...)->update(...)`) bypass model events and can easily modify records outside the user's scope if queries aren't properly bounded.

---

## 8. Frontend Architecture
`Confidence: High — React component analyzed`

- **State Management:** Handled locally via React `useState`. Bulk selection is managed with a `Set<number>`.
- **False Security Patterns:**
  - The UI hides "Edit" and "Delete" buttons via `canManage = user?.role === 'principal' || ...`. The backend *does* enforce this correctly via Policies, so this is safe.
  - The frontend bounds maximum marks on inputs: `max={work.maxMarks}`. The backend also strictly enforces this, which is excellent.

---

## 9. Performance Audit
`Confidence: High — Controller queries analyzed`

- **N+1 Queries:** Successfully avoided. `CCEWorkController@show` uses `with(['subject.classRoom', 'submissions.student.user'])`.
- **Student Marks Aggregation:** `CCESubmissionController@studentMarks` uses a raw SQL `DB::table()->join()->select()->groupBy()` query. This is a highly optimized approach that processes the aggregation in the database rather than hydrating hundreds of Eloquent models.
- **Bottlenecks:** In `studentMarks`, computing final percentages in memory for thousands of students might get heavy, but is necessary for business logic (handling `final_max_marks` conversion).

---

## 10. Module Smell Detection
`Confidence: High`

| Smell | Present? | Evidence | Severity |
|---|---|---|---|
| God Controller | ❌ | Methods are concise. | - |
| Authorization Scatter | ⚠️ | Subject ownership is missing in `store`, checked via Policy in `update`/`delete`. | Medium |
| Missing Abstractions | ⚠️ | No FormRequests. Validation logic clutters controllers. | Low |
| Query Leakage | ❌ | Queries are kept in Controllers. | - |

---

## 11. Edge Case Analysis
`Confidence: Medium`

| Scenario | Current Behavior | Risk |
|---|---|---|
| Marks exceeding Max Marks | Backend blocks it natively during evaluation. | Low |
| Submitting a file after it's evaluated | The controller doesn't explicitly block `submitWork` if `status === 'evaluated'`. | Medium |
| Bulk evaluate with empty array | Validation `min:1` prevents it. | Low |

---

## 12. Improvement Roadmap

**🔴 Immediate (Critical — fix before production):**
- **Fix Bulk Evaluate IDOR** — Priority Score: 72 — Validate all `submission_ids` belong to a single `work_id` that the user is authorized to evaluate.

**🟠 Short-term (High — fix this sprint):**
- **Fix Work Creation Subject Spoofing** — Priority Score: 49 — Verify `subject_id` ownership in `CCEWorkController@store`.

**🟡 Medium-term:**
- **Prevent Post-Evaluation Modification** — Block students from re-submitting files if their submission is already graded (unless explicitly allowed).
- **Extract FormRequests** — Move validation logic out of controllers to clean up code structure.

---

## 13. Production Readiness Score ⚠️

| Dimension | Score | Key Evidence |
|---|---|---|
| Security | 5/10 | Critical IDOR present in bulk-evaluate. |
| Architecture | 8/10 | Clean separation, good use of Eloquent relations. |
| Maintainability | 7/10 | Controllers are slightly fat with validation. |
| Performance | 9/10 | Excellent raw DB aggregations and eager loading. |
| Authorization | 6/10 | Policies are good, but controller execution is flawed for bulk/creation. |

**Overall:** 7.0 / 10
**Audit Reliability:** 90%
