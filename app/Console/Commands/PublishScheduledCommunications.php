<?php

namespace App\Console\Commands;

use App\Models\Communication;
use App\Services\CommunicationPublisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class PublishScheduledCommunications extends Command
{
    protected $signature = 'communications:publish';

    protected $description = 'Publica comunicações agendadas e avisa seus destinatários';

    public function handle(CommunicationPublisher $publisher): int
    {
        if (! Schema::hasTable('communications')) {
            return self::SUCCESS;
        }

        $published = 0;
        $failures = 0;

        Communication::query()
            ->where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->orderBy('id')
            ->each(function (Communication $communication) use ($publisher, &$published, &$failures): void {
                $failures += $publisher->publish($communication);
                $published++;
            });

        $this->info("{$published} comunicação(ões) publicada(s); {$failures} falha(s) de e-mail.");

        return self::SUCCESS;
    }
}
