<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'app:test-email',
    description: 'Send a test email to verify mailer configuration',
)]
class TestEmailCommand extends Command
{
    public function __construct(
        private readonly MailerInterface $mailer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('to', InputArgument::REQUIRED, 'Recipient email address')
            ->addArgument('from', InputArgument::OPTIONAL, 'Sender email address', 'noreply@honeyguide.org');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $to = $input->getArgument('to');
        $from = $input->getArgument('from');

        $io->info("Sending test email from {$from} to {$to}...");

        $email = (new Email())
            ->from($from)
            ->to($to)
            ->subject('Test Email from Honeyguide Projects')
            ->html($this->getEmailContent());

        try {
            $this->mailer->send($email);
            $io->success("Test email sent successfully to {$to}!");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("Failed to send email: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function getEmailContent(): string
    {
        $timestamp = date('Y-m-d H:i:s');
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Test Email</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h1 style="color: #2563eb;">Test Email</h1>
        <p>This is a test email from your Symfony application.</p>
        <p>If you received this email, your mailer configuration is working correctly!</p>
        <p><strong>Sent at:</strong> {$timestamp}</p>
        <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">
        <p style="color: #666; font-size: 12px;">
            This is an automated test email from Honeyguide Projects.
        </p>
    </div>
</body>
</html>
HTML;
    }
}
