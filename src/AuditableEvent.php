<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AuditableEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The data.
     *
     * @var array data
     */
    public $data;

    /**
     * Create a new event instance.
     *
     * @param  array  $data
     * @return void
     */
    public function __construct(array $data)
    {
        $this->data = $data;

        // handle somewhere and store with
        /*
        \Storage::disk($disk)
            ->getDriver()
            ->getAdapter()
            ->getClient()
            ->upload(
                $bucket,
                $data['path'],
                gzencode(json_encode($data['body'])),
                'private',
                [
                    'params' => [
                        'ContentType'     => 'application/json',
                        'ContentEncoding' => 'gzip',
                    ],
                ]
            );

        */
    }
}
