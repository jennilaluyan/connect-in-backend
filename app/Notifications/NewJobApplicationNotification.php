<?php

namespace App\Notifications;

use App\Models\JobApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewJobApplicationNotification extends Notification
{
    use Queueable;

    public $application;

    public function __construct(JobApplication $application)
    {
        $this->application = $application;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_application', // Tipe untuk filter di frontend
            'applicant_name' => $this->application->applicant->name,
            'job_title' => $this->application->jobPosting->title,
            'application_id' => $this->application->id,
            'message' => "Pelamar baru, {$this->application->applicant->name}, telah melamar untuk posisi {$this->application->jobPosting->title}."
        ];
    }
}