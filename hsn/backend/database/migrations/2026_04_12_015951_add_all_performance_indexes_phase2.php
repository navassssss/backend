<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── students ──────────────────────────────────────────────
        Schema::table('students', function (Blueprint $table) {
            if (!$this->indexExists('students', 'students_class_id_index')) {
                $table->index('class_id', 'students_class_id_index');
            }
            if (!$this->indexExists('students', 'students_user_id_index')) {
                $table->index('user_id', 'students_user_id_index');
            }
            if (!$this->indexExists('students', 'students_roll_number_index')) {
                $table->index('roll_number', 'students_roll_number_index');
            }
        });

        // ── achievements ─────────────────────────────────────────
        Schema::table('achievements', function (Blueprint $table) {
            if (!$this->indexExists('achievements', 'achievements_student_status_index')) {
                $table->index(['student_id', 'status'], 'achievements_student_status_index');
            }
            if (!$this->indexExists('achievements', 'achievements_status_approved_by_index')) {
                $table->index(['status', 'approved_by'], 'achievements_status_approved_by_index');
            }
        });

        // ── achievement_attachments ───────────────────────────────
        Schema::table('achievement_attachments', function (Blueprint $table) {
            if (!$this->indexExists('achievement_attachments', 'achievement_attachments_achievement_id_index')) {
                $table->index('achievement_id', 'achievement_attachments_achievement_id_index');
            }
        });

        // ── announcement_reads ────────────────────────────────────
        if (Schema::hasTable('announcement_reads')) {
            Schema::table('announcement_reads', function (Blueprint $table) {
                if (!$this->indexExists('announcement_reads', 'ann_reads_user_announcement_unique')) {
                    $table->unique(['announcement_id', 'user_id'], 'ann_reads_user_announcement_unique');
                }
            });
        }

        // ── cce_works ─────────────────────────────────────────────
        Schema::table('cce_works', function (Blueprint $table) {
            if (!$this->indexExists('cce_works', 'cce_works_subject_due_index')) {
                $table->index(['subject_id', 'due_date'], 'cce_works_subject_due_index');
            }
        });

        // ── cce_submissions ───────────────────────────────────────
        Schema::table('cce_submissions', function (Blueprint $table) {
            if (!$this->indexExists('cce_submissions', 'cce_sub_work_student_unique')) {
                $table->unique(['work_id', 'student_id'], 'cce_sub_work_student_unique');
            }
            if (!$this->indexExists('cce_submissions', 'cce_sub_student_status_index')) {
                $table->index(['student_id', 'status'], 'cce_sub_student_status_index');
            }
            if (!$this->indexExists('cce_submissions', 'cce_sub_work_status_index')) {
                $table->index(['work_id', 'status'], 'cce_sub_work_status_index');
            }
        });

        // ── duties ───────────────────────────────────────────────
        Schema::table('duties', function (Blueprint $table) {
            if (!$this->indexExists('duties', 'duties_created_by_status_index')) {
                $table->index(['created_by', 'status'], 'duties_created_by_status_index');
            }
        });

        // ── fee_payments ─────────────────────────────────────────
        Schema::table('fee_payments', function (Blueprint $table) {
            if (!$this->indexExists('fee_payments', 'fee_payments_student_date_index')) {
                $table->index(['student_id', 'payment_date'], 'fee_payments_student_date_index');
            }
        });

        // ── fee_payment_allocations ──────────────────────────────
        Schema::table('fee_payment_allocations', function (Blueprint $table) {
            if (!$this->indexExists('fee_payment_allocations', 'fee_alloc_student_year_month_index')) {
                $table->index(['student_id', 'year', 'month'], 'fee_alloc_student_year_month_index');
            }
            if (!$this->indexExists('fee_payment_allocations', 'fee_alloc_payment_id_index')) {
                $table->index('fee_payment_id', 'fee_alloc_payment_id_index');
            }
        });

        // ── monthly_fee_plans ─────────────────────────────────────
        Schema::table('monthly_fee_plans', function (Blueprint $table) {
            if (!$this->indexExists('monthly_fee_plans', 'fee_plans_student_year_month_unique')) {
                $table->unique(['student_id', 'year', 'month'], 'fee_plans_student_year_month_unique');
            }
        });

        // ── issues ────────────────────────────────────────────────
        Schema::table('issues', function (Blueprint $table) {
            if (!$this->indexExists('issues', 'issues_created_by_status_index')) {
                $table->index(['created_by', 'status'], 'issues_created_by_status_index');
            }
            if (!$this->indexExists('issues', 'issues_responsible_status_index')) {
                $table->index(['responsible_user_id', 'status'], 'issues_responsible_status_index');
            }
        });

        // ── issue_actions ─────────────────────────────────────────
        Schema::table('issue_actions', function (Blueprint $table) {
            if (!$this->indexExists('issue_actions', 'issue_actions_issue_created_index')) {
                $table->index(['issue_id', 'created_at'], 'issue_actions_issue_created_index');
            }
        });

        // ── issue_comments ────────────────────────────────────────
        Schema::table('issue_comments', function (Blueprint $table) {
            if (!$this->indexExists('issue_comments', 'issue_comments_issue_created_index')) {
                $table->index(['issue_id', 'created_at'], 'issue_comments_issue_created_index');
            }
        });

        // ── push_subscriptions ────────────────────────────────────
        Schema::table('push_subscriptions', function (Blueprint $table) {
            if (!$this->indexExists('push_subscriptions', 'push_subs_user_id_index')) {
                $table->index('user_id', 'push_subs_user_id_index');
            }
        });

        // ── reports ───────────────────────────────────────────────
        Schema::table('reports', function (Blueprint $table) {
            if (!$this->indexExists('reports', 'reports_teacher_status_index')) {
                $table->index(['teacher_id', 'status'], 'reports_teacher_status_index');
            }
        });

        // ── report_attachments ────────────────────────────────────
        Schema::table('report_attachments', function (Blueprint $table) {
            if (!$this->indexExists('report_attachments', 'report_attachments_report_id_index')) {
                $table->index('report_id', 'report_attachments_report_id_index');
            }
        });

        // ── report_comments ───────────────────────────────────────
        Schema::table('report_comments', function (Blueprint $table) {
            if (!$this->indexExists('report_comments', 'report_comments_report_id_index')) {
                $table->index('report_id', 'report_comments_report_id_index');
            }
        });

        // ── subjects ──────────────────────────────────────────────
        Schema::table('subjects', function (Blueprint $table) {
            if (!$this->indexExists('subjects', 'subjects_class_teacher_index')) {
                $table->index(['class_id', 'teacher_id'], 'subjects_class_teacher_index');
            }
        });

        // ── tasks ─────────────────────────────────────────────────
        Schema::table('tasks', function (Blueprint $table) {
            if (!$this->indexExists('tasks', 'tasks_assigned_to_status_index')) {
                $table->index(['assigned_to', 'status'], 'tasks_assigned_to_status_index');
            }
            if (!$this->indexExists('tasks', 'tasks_status_scheduled_date_index')) {
                $table->index(['status', 'scheduled_date'], 'tasks_status_scheduled_date_index');
            }
        });

        // ── users ─────────────────────────────────────────────────
        Schema::table('users', function (Blueprint $table) {
            if (!$this->indexExists('users', 'users_role_department_index')) {
                $table->index(['role', 'department'], 'users_role_department_index');
            }
        });

        // ── notifications (system table) ──────────────────────────
        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                if (!$this->indexExists('notifications', 'notif_notifiable_read_created_index')) {
                    $table->index(['notifiable_id', 'read_at', 'created_at'], 'notif_notifiable_read_created_index');
                }
            });
        }
    }

    public function down(): void
    {
        $drops = [
            'students'                 => ['students_class_id_index', 'students_user_id_index', 'students_roll_number_index'],
            'achievements'             => ['achievements_student_status_index', 'achievements_status_approved_by_index'],
            'achievement_attachments'  => ['achievement_attachments_achievement_id_index'],
            'announcement_reads'       => ['ann_reads_user_announcement_unique'],
            'cce_works'               => ['cce_works_subject_due_index'],
            'cce_submissions'          => ['cce_sub_work_student_unique', 'cce_sub_student_status_index', 'cce_sub_work_status_index'],
            'duties'                   => ['duties_created_by_status_index'],
            'fee_payments'             => ['fee_payments_student_date_index'],
            'fee_payment_allocations'  => ['fee_alloc_student_year_month_index', 'fee_alloc_payment_id_index'],
            'monthly_fee_plans'        => ['fee_plans_student_year_month_unique'],
            'issues'                   => ['issues_created_by_status_index', 'issues_responsible_status_index'],
            'issue_actions'            => ['issue_actions_issue_created_index'],
            'issue_comments'           => ['issue_comments_issue_created_index'],
            'push_subscriptions'       => ['push_subs_user_id_index'],
            'reports'                  => ['reports_teacher_status_index'],
            'report_attachments'       => ['report_attachments_report_id_index'],
            'report_comments'          => ['report_comments_report_id_index'],
            'subjects'                 => ['subjects_class_teacher_index'],
            'tasks'                    => ['tasks_assigned_to_status_index', 'tasks_status_scheduled_date_index'],
            'users'                    => ['users_role_department_index'],
            'notifications'            => ['notif_notifiable_read_created_index'],
        ];

        foreach ($drops as $table => $indexes) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $t) use ($indexes) {
                    foreach ($indexes as $idx) {
                        try { $t->dropIndex($idx); } catch (\Exception $e) {}
                    }
                });
            }
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        try {
            $indexNames = collect(
                \DB::getSchemaBuilder()->getIndexes($table)
            )->pluck('name');
            return $indexNames->contains($indexName);
        } catch (\Throwable) {
            return false;
        }
    }
};
