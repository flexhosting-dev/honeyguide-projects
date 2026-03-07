<?php

namespace App\Command;

use Minishlink\WebPush\VAPID;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-vapid-keys',
    description: 'Generate VAPID keys for Web Push notifications',
)]
class GenerateVapidKeysCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Generating VAPID Keys for Web Push');

        try {
            $keys = VAPID::createVapidKeys();

            $io->success('VAPID keys generated successfully!');
            $io->section('Add these to your .env file:');

            $io->writeln([
                '',
                '# Web Push Notifications',
                'VAPID_PUBLIC_KEY=' . $keys['publicKey'],
                'VAPID_PRIVATE_KEY=' . $keys['privateKey'],
                'VAPID_SUBJECT=mailto:noreply@yourdomain.com',
                '',
            ]);

            $io->note([
                'The VAPID_SUBJECT should be a mailto: URL or your website URL.',
                'Keep the private key secret - never commit it to version control.',
                'The public key will be exposed to the frontend (this is normal and safe).',
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to generate VAPID keys: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
