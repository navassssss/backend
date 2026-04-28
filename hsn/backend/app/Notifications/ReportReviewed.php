<?php

namespace App\Notifications;

use App\Models\Report;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReportReviewed extends Notification
{
    use Queueable;

    public $report;
    public $reviewer;
    public $status;

    public function __construct(Report $report, User $reviewer)
    {
        $this->report = $report;
        $this->reviewer = $reviewer;
        $this->status = $report->status;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        $msg = $this->status === 'approved' 
            ? "Your report for {$this->report->task->title} was approved."
            : "Your report for {$this->report->task->title} was rejected. Please review.";

        return [
            'title' => 'Report ' . ucfirst($this->status),
            'message' => $msg,
            'action_url' => "/reports/{$this->report->id}",
            'type' => $this->status === 'approved' ? 'success' : 'error',
        ];
    }
}
