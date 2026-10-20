<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserInvitation extends Notification
{
    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your PSC CRM invitation')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('You have been invited to PSC CRM. Set your password to access your account.')
            ->action('Set Password', route('invitation.accept', ['token' => $this->token, 'email' => $notifiable->email]))
            ->line('This link expires in '.config('auth.passwords.users.expire').' minutes. Contact your administrator if you need a new invitation.');
    }
}
