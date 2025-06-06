<?php

namespace App\Notifications;

use App\Models\JobApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ApplicationStatusUpdatedNotification extends Notification
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
        $status = $this->application->status;
        $jobTitle = $this->application->jobPosting->title;
        $message = "Status lamaran Anda untuk posisi {$jobTitle} telah diperbarui menjadi: " . ucfirst(str_replace('_', ' ', $status)) . ".";

        if ($status === 'shortlisted' || $status === 'hired') {
            $message = "Selamat! Lamaran Anda untuk posisi {$jobTitle} telah diterima dan statusnya sekarang adalah " . ucfirst(str_replace('_', ' ', $status)) . ".";
        } elseif ($status === 'rejected') {
            $message = "Kami informasikan bahwa lamaran Anda untuk posisi {$jobTitle} telah ditolak. Tetap semangat!";
        }

        return [
            'type' => 'application_status', // Tipe untuk filter di frontend
            'job_title' => $jobTitle,
            'application_status' => $status,
            'application_id' => $this->application->id,
            'message' => $message,
        ];
    }
}