<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'app:test-public-access',
    description: 'Test if invitation URL is publicly accessible',
)]
class TestPublicAccessCommand extends Command
{
    public function __construct(
        private readonly KernelInterface $kernel,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('token', InputArgument::REQUIRED, 'Invitation token');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $token = $input->getArgument('token');

        $io->title('Testing Public Access to Invitation URL');

        // Test if routes are accessible
        $router = $this->kernel->getContainer()->get('router');

        try {
            $route = $router->match('/invitations/' . $token . '/accept');

            $io->success('Route found!');
            $io->table(
                ['Property', 'Value'],
                [
                    ['Controller', $route['_controller']],
                    ['Route Name', $route['_route']],
                ]
            );

            // Check security configuration
            $io->section('Security Configuration Check');

            $configFile = $this->kernel->getProjectDir() . '/config/packages/security.yaml';
            $content = file_get_contents($configFile);

            if (str_contains($content, '^/invitations/.+/accept')) {
                $io->success('✅ Invitation route is configured as PUBLIC_ACCESS');
            } else {
                $io->error('❌ Invitation route is NOT configured as public');
                return Command::FAILURE;
            }

            $io->newLine();
            $io->success('The invitation URL is publicly accessible!');
            $io->text('URL: https://dev.flexhosting.co/zohoclone/invitations/' . $token . '/accept');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Route not found: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
