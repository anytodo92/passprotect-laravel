<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LinkDownload extends Notification
{
    use Queueable;

    protected $fromAddress;
    protected $name;
    protected $mailText;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($fromAddress, $name, $mailText)
    {
        $this->fromAddress = $fromAddress;
        $this->name = $name;
        $this->mailText = $mailText;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
                    ->from($this->fromAddress, $this->name)
                    ->subject('Your link was accessed')
                    ->line($this->mailText);
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
