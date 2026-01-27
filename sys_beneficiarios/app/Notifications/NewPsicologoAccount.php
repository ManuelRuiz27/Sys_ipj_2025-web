<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewPsicologoAccount extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private string $temporaryPassword)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appUrl = config('app.url', url('/'));
        return (new MailMessage)
            ->subject('Tu cuenta de Psicólogo — Salud360')
            ->greeting('Hola '.$notifiable->name)
            ->line('Se ha creado tu cuenta de Psicólogo en el sistema Salud360.')
            ->line('Correo: '.$notifiable->email)
            ->line('Contraseña temporal: '.$this->temporaryPassword)
            ->action('Ingresar al sistema', $appUrl)
            ->line('Por seguridad, cambia tu contraseña después de iniciar sesión.');
    }
}

