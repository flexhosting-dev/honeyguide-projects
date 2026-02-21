<?php

namespace App\Service;

use App\Entity\User;
use App\Enum\NotificationType;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class NotificationEmailService
{
    private Address $fromAddress;

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire('%env(MAILER_FROM_ADDRESS)%')]
        string $fromEmail,
        #[Autowire('%env(MAILER_FROM_NAME)%')]
        string $fromName,
    ) {
        $this->fromAddress = new Address($fromEmail, $fromName);
    }

    public function sendNotificationEmail(
        User $recipient,
        NotificationType $type,
        ?User $actor,
        string $entityType,
        UuidInterface $entityId,
        ?string $entityName,
        ?array $data,
    ): void {
        if (!$recipient->shouldReceiveNotification($type, 'email')) {
            return;
        }

        $subject = $this->getSubject($type, $actor, $entityName, $data);
        $html = $this->renderEmail($type, $recipient, $actor, $entityType, $entityId, $entityName, $data);

        if ($subject === null || $html === null) {
            return;
        }

        $email = (new Email())
            ->from($this->fromAddress)
            ->to($recipient->getEmail())
            ->subject($subject)
            ->html($html);

        $this->mailer->send($email);
    }

    public function sendProjectInvitationEmail(
        string $toEmail,
        string $projectName,
        string $invitedByName,
        string $roleName,
        string $token,
    ): void {
        $acceptUrl = $this->urlGenerator->generate(
            'app_invitation_accept',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $settingsUrl = $this->urlGenerator->generate('app_login', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Project Invitation</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h1 style="color: #2563eb;">You're Invited to Join a Project</h1>
        <p>Hi,</p>
        <p>{$this->escape($invitedByName)} has invited you to join the project <strong>{$this->escape($projectName)}</strong> on Honeyguide Projects.</p>
        <p><strong>Your role:</strong> {$this->escape($roleName)}</p>
        <p style="margin: 30px 0;">
            <a href="{$this->escape($acceptUrl)}"
               style="background-color: #2563eb; color: white; padding: 12px 24px;
                      text-decoration: none; border-radius: 6px; display: inline-block;">
                Accept Invitation
            </a>
        </p>
        <p style="color: #666; font-size: 14px;">This invitation will expire in 7 days.</p>
        <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">
        <p style="color: #666; font-size: 12px;">
            This is an automated invitation from Honeyguide Projects.
        </p>
    </div>
</body>
</html>
HTML;

        $email = (new Email())
            ->from($this->fromAddress)
            ->to($toEmail)
            ->subject("You've been invited to join: " . $projectName)
            ->html($html);

        $this->mailer->send($email);
    }

    private function getSubject(
        NotificationType $type,
        ?User $actor,
        ?string $entityName,
        ?array $data,
    ): ?string {
        $actorName = $actor?->getFullName() ?? 'Someone';

        return match ($type) {
            NotificationType::TASK_ASSIGNED => "You've been assigned to: " . ($entityName ?? 'a task'),
            NotificationType::TASK_UNASSIGNED => "You've been unassigned from: " . ($entityName ?? 'a task'),
            NotificationType::TASK_COMPLETED => 'Task completed: ' . ($entityName ?? 'a task'),
            NotificationType::TASK_DUE_SOON => 'Task due soon: ' . ($entityName ?? 'a task'),
            NotificationType::TASK_OVERDUE => 'Task overdue: ' . ($entityName ?? 'a task'),
            NotificationType::TASK_STATUS_CHANGED => 'Task status changed: ' . ($entityName ?? 'a task'),
            NotificationType::COMMENT_ADDED => 'New comment on: ' . ($entityName ?? 'a task'),
            NotificationType::COMMENT_REPLY => 'Reply to your comment on: ' . ($entityName ?? 'a task'),
            NotificationType::MENTIONED => $actorName . ' mentioned you in: ' . ($entityName ?? 'a comment'),
            NotificationType::PROJECT_INVITED => "You've been invited to: " . ($entityName ?? 'a project'),
            NotificationType::PROJECT_REMOVED => 'Removed from project: ' . ($entityName ?? 'a project'),
            NotificationType::MILESTONE_DUE => 'Milestone due: ' . ($entityName ?? 'a milestone'),
            NotificationType::ATTACHMENT_ADDED => 'New attachment on: ' . ($entityName ?? 'a task'),
            NotificationType::REGISTRATION_REQUEST => 'New Registration Request: ' . ($entityName ?? 'Unknown'),
            NotificationType::PROJECT_INVITATION_APPROVAL_REQUIRED => 'Project Invitation Approval Required: ' . ($entityName ?? 'a project'),
            NotificationType::PROJECT_INVITATION_APPROVED => 'Project Invitation Approved: ' . ($entityName ?? 'a project'),
        };
    }

    private function renderEmail(
        NotificationType $type,
        User $recipient,
        ?User $actor,
        string $entityType,
        UuidInterface $entityId,
        ?string $entityName,
        ?array $data,
    ): ?string {
        $actorName = $actor?->getFullName() ?? 'Someone';
        $firstName = $recipient->getFirstName();
        $settingsUrl = $this->urlGenerator->generate('app_settings_notifications', [], UrlGeneratorInterface::ABSOLUTE_URL);

        return match ($type) {
            NotificationType::TASK_ASSIGNED => $this->renderTaskAssignedEmail($firstName, $actorName, $entityName, $data, $settingsUrl),
            NotificationType::TASK_UNASSIGNED => $this->renderTaskUnassignedEmail($firstName, $actorName, $entityName, $data, $settingsUrl),
            NotificationType::TASK_COMPLETED => $this->renderTaskCompletedEmail($firstName, $actorName, $entityName, $data, $settingsUrl),
            NotificationType::TASK_DUE_SOON => $this->renderTaskDueSoonEmail($firstName, $entityName, $data, $settingsUrl),
            NotificationType::TASK_OVERDUE => $this->renderTaskOverdueEmail($firstName, $entityName, $data, $settingsUrl),
            NotificationType::TASK_STATUS_CHANGED => $this->renderTaskStatusChangedEmail($firstName, $actorName, $entityName, $data, $settingsUrl),
            NotificationType::COMMENT_ADDED => $this->renderCommentAddedEmail($firstName, $actorName, $entityName, $data, $settingsUrl),
            NotificationType::COMMENT_REPLY => $this->renderCommentReplyEmail($firstName, $actorName, $entityName, $data, $settingsUrl),
            NotificationType::MENTIONED => $this->renderMentionedEmail($firstName, $actorName, $entityName, $data, $settingsUrl),
            NotificationType::PROJECT_INVITED => $this->renderProjectInvitedEmail($firstName, $actorName, $entityName, $data, $settingsUrl),
            NotificationType::PROJECT_REMOVED => $this->renderProjectRemovedEmail($firstName, $entityName, $settingsUrl),
            NotificationType::MILESTONE_DUE => $this->renderMilestoneDueEmail($firstName, $entityName, $data, $settingsUrl),
            NotificationType::ATTACHMENT_ADDED => $this->renderAttachmentAddedEmail($firstName, $actorName, $entityName, $data, $settingsUrl),
            NotificationType::REGISTRATION_REQUEST => $this->renderRegistrationRequestEmail($firstName, $entityName, $data, $settingsUrl),
            NotificationType::PROJECT_INVITATION_APPROVAL_REQUIRED => $this->renderInvitationApprovalRequiredEmail($firstName, $actorName, $entityName, $data, $settingsUrl),
            NotificationType::PROJECT_INVITATION_APPROVED => $this->renderInvitationApprovedEmail($firstName, $entityName, $data, $settingsUrl),
        };
    }

    private function renderTaskAssignedEmail(string $firstName, string $actorName, ?string $taskName, ?array $data, string $settingsUrl): string
    {
        $taskUrl = $this->getTaskUrl($data);
        $projectName = $data['projectName'] ?? null;
        $projectInfo = $projectName ? "<p><strong>Project:</strong> {$this->escape($projectName)}</p>" : '';

        return $this->renderEmailTemplate(
            "You've been assigned to a task",
            $firstName,
            "<p>{$this->escape($actorName)} has assigned you to the task <strong>{$this->escape($taskName ?? 'Untitled')}</strong>.</p>{$projectInfo}",
            $taskUrl,
            'View Task',
            $settingsUrl,
        );
    }

    private function renderTaskUnassignedEmail(string $firstName, string $actorName, ?string $taskName, ?array $data, string $settingsUrl): string
    {
        $taskUrl = $this->getTaskUrl($data);

        return $this->renderEmailTemplate(
            "You've been unassigned from a task",
            $firstName,
            "<p>{$this->escape($actorName)} has removed you from the task <strong>{$this->escape($taskName ?? 'Untitled')}</strong>.</p>",
            $taskUrl,
            'View Task',
            $settingsUrl,
        );
    }

    private function renderTaskCompletedEmail(string $firstName, string $actorName, ?string $taskName, ?array $data, string $settingsUrl): string
    {
        $taskUrl = $this->getTaskUrl($data);

        return $this->renderEmailTemplate(
            'Task Completed',
            $firstName,
            "<p>{$this->escape($actorName)} has marked the task <strong>{$this->escape($taskName ?? 'Untitled')}</strong> as complete.</p>",
            $taskUrl,
            'View Task',
            $settingsUrl,
        );
    }

    private function renderTaskDueSoonEmail(string $firstName, ?string $taskName, ?array $data, string $settingsUrl): string
    {
        $taskUrl = $this->getTaskUrl($data);
        $dueDate = isset($data['dueDate']) ? ' on ' . $this->escape($data['dueDate']) : ' soon';

        return $this->renderEmailTemplate(
            'Task Due Soon',
            $firstName,
            "<p>Your task <strong>{$this->escape($taskName ?? 'Untitled')}</strong> is due{$dueDate}.</p>",
            $taskUrl,
            'View Task',
            $settingsUrl,
        );
    }

    private function renderTaskOverdueEmail(string $firstName, ?string $taskName, ?array $data, string $settingsUrl): string
    {
        $taskUrl = $this->getTaskUrl($data);
        $dueDate = isset($data['dueDate']) ? ' It was due on ' . $this->escape($data['dueDate']) . '.' : '';

        return $this->renderEmailTemplate(
            'Task Overdue',
            $firstName,
            "<p>Your task <strong>{$this->escape($taskName ?? 'Untitled')}</strong> is overdue.{$dueDate}</p>",
            $taskUrl,
            'View Task',
            $settingsUrl,
        );
    }

    private function renderTaskStatusChangedEmail(string $firstName, string $actorName, ?string $taskName, ?array $data, string $settingsUrl): string
    {
        $taskUrl = $this->getTaskUrl($data);
        $oldStatus = $data['oldStatus'] ?? null;
        $newStatus = $data['newStatus'] ?? null;
        $statusInfo = ($oldStatus && $newStatus) ? " from <strong>{$this->escape($oldStatus)}</strong> to <strong>{$this->escape($newStatus)}</strong>" : '';

        return $this->renderEmailTemplate(
            'Task Status Changed',
            $firstName,
            "<p>{$this->escape($actorName)} changed the status of <strong>{$this->escape($taskName ?? 'Untitled')}</strong>{$statusInfo}.</p>",
            $taskUrl,
            'View Task',
            $settingsUrl,
        );
    }

    private function renderCommentAddedEmail(string $firstName, string $actorName, ?string $taskName, ?array $data, string $settingsUrl): string
    {
        $taskUrl = $this->getTaskUrl($data);
        $commentPreview = isset($data['commentPreview']) ? '<blockquote style="border-left: 3px solid #2563eb; margin: 16px 0; padding-left: 16px; color: #666;">' . $this->escape($data['commentPreview']) . '</blockquote>' : '';

        return $this->renderEmailTemplate(
            'New Comment',
            $firstName,
            "<p>{$this->escape($actorName)} commented on <strong>{$this->escape($taskName ?? 'Untitled')}</strong>.</p>{$commentPreview}",
            $taskUrl,
            'View Comment',
            $settingsUrl,
        );
    }

    private function renderCommentReplyEmail(string $firstName, string $actorName, ?string $taskName, ?array $data, string $settingsUrl): string
    {
        $taskUrl = $this->getTaskUrl($data);
        $commentPreview = isset($data['commentPreview']) ? '<blockquote style="border-left: 3px solid #2563eb; margin: 16px 0; padding-left: 16px; color: #666;">' . $this->escape($data['commentPreview']) . '</blockquote>' : '';

        return $this->renderEmailTemplate(
            'Reply to Your Comment',
            $firstName,
            "<p>{$this->escape($actorName)} replied to your comment on <strong>{$this->escape($taskName ?? 'Untitled')}</strong>.</p>{$commentPreview}",
            $taskUrl,
            'View Reply',
            $settingsUrl,
        );
    }

    private function renderMentionedEmail(string $firstName, string $actorName, ?string $taskName, ?array $data, string $settingsUrl): string
    {
        $taskUrl = $this->getTaskUrl($data);
        $commentPreview = isset($data['commentPreview']) ? '<blockquote style="border-left: 3px solid #2563eb; margin: 16px 0; padding-left: 16px; color: #666;">' . $this->escape($data['commentPreview']) . '</blockquote>' : '';

        return $this->renderEmailTemplate(
            'You Were Mentioned',
            $firstName,
            "<p>{$this->escape($actorName)} mentioned you in a comment on <strong>{$this->escape($taskName ?? 'Untitled')}</strong>.</p>{$commentPreview}",
            $taskUrl,
            'View Comment',
            $settingsUrl,
        );
    }

    private function renderProjectInvitedEmail(string $firstName, string $actorName, ?string $projectName, ?array $data, string $settingsUrl): string
    {
        $projectUrl = $this->getProjectUrl($data);
        $role = isset($data['role']) ? '<p><strong>Your role:</strong> ' . $this->escape($data['role']) . '</p>' : '';

        return $this->renderEmailTemplate(
            'Project Invitation',
            $firstName,
            "<p>{$this->escape($actorName)} has invited you to join the project <strong>{$this->escape($projectName ?? 'Untitled')}</strong>.</p>{$role}",
            $projectUrl,
            'View Project',
            $settingsUrl,
        );
    }

    private function renderProjectRemovedEmail(string $firstName, ?string $projectName, string $settingsUrl): string
    {
        return $this->renderEmailTemplate(
            'Removed from Project',
            $firstName,
            "<p>You have been removed from the project <strong>{$this->escape($projectName ?? 'Untitled')}</strong>.</p>",
            null,
            null,
            $settingsUrl,
        );
    }

    private function renderMilestoneDueEmail(string $firstName, ?string $milestoneName, ?array $data, string $settingsUrl): string
    {
        $projectUrl = $this->getProjectUrl($data);
        $dueDate = isset($data['dueDate']) ? '<p><strong>Due date:</strong> ' . $this->escape($data['dueDate']) . '</p>' : '';

        return $this->renderEmailTemplate(
            'Milestone Due',
            $firstName,
            "<p>The milestone <strong>{$this->escape($milestoneName ?? 'Untitled')}</strong> is approaching its due date.</p>{$dueDate}",
            $projectUrl,
            'View Project',
            $settingsUrl,
        );
    }

    private function renderAttachmentAddedEmail(string $firstName, string $actorName, ?string $taskName, ?array $data, string $settingsUrl): string
    {
        $taskUrl = $this->getTaskUrl($data);
        $fileName = isset($data['fileName']) ? '<p><strong>File:</strong> ' . $this->escape($data['fileName']) . '</p>' : '';

        return $this->renderEmailTemplate(
            'New Attachment',
            $firstName,
            "<p>{$this->escape($actorName)} added an attachment to <strong>{$this->escape($taskName ?? 'Untitled')}</strong>.</p>{$fileName}",
            $taskUrl,
            'View Task',
            $settingsUrl,
        );
    }

    private function renderRegistrationRequestEmail(string $firstName, ?string $userName, ?array $data, string $settingsUrl): string
    {
        $usersUrl = $this->urlGenerator->generate(
            'admin_users_index',
            ['tab' => 'pending'],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $email = $data['email'] ?? 'Unknown';
        $domain = $data['domain'] ?? 'Unknown';
        $type = $data['type'] ?? 'Unknown';

        return $this->renderEmailTemplate(
            'New Registration Request',
            $firstName,
            "<p>A new user has requested access to Honeyguide Projects:</p>
            <table style=\"width: 100%; margin: 20px 0; border-collapse: collapse;\">
                <tr>
                    <td style=\"padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;\">Name</td>
                    <td style=\"padding: 10px; border-bottom: 1px solid #eee;\">{$this->escape($userName ?? 'Unknown')}</td>
                </tr>
                <tr>
                    <td style=\"padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;\">Email</td>
                    <td style=\"padding: 10px; border-bottom: 1px solid #eee;\">{$this->escape($email)}</td>
                </tr>
                <tr>
                    <td style=\"padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;\">Domain</td>
                    <td style=\"padding: 10px; border-bottom: 1px solid #eee;\">{$this->escape($domain)}</td>
                </tr>
                <tr>
                    <td style=\"padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;\">Registration Type</td>
                    <td style=\"padding: 10px; border-bottom: 1px solid #eee;\">{$this->escape($type)}</td>
                </tr>
            </table>",
            $usersUrl,
            'Review Request',
            $settingsUrl,
        );
    }

    private function renderInvitationApprovalRequiredEmail(string $firstName, string $actorName, ?string $projectName, ?array $data, string $settingsUrl): string
    {
        $invitationsUrl = $this->urlGenerator->generate(
            'admin_invitations_pending',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $inviteeEmail = $data['inviteeEmail'] ?? 'Unknown';
        $role = $data['role'] ?? 'Unknown';

        return $this->renderEmailTemplate(
            'Project Invitation Approval Required',
            $firstName,
            "<p>{$this->escape($actorName)} has invited a user from a restricted domain to join the project <strong>{$this->escape($projectName ?? 'Untitled')}</strong>. Your approval is required.</p>
            <table style=\"width: 100%; margin: 20px 0; border-collapse: collapse;\">
                <tr>
                    <td style=\"padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;\">Invitee Email</td>
                    <td style=\"padding: 10px; border-bottom: 1px solid #eee;\">{$this->escape($inviteeEmail)}</td>
                </tr>
                <tr>
                    <td style=\"padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;\">Project</td>
                    <td style=\"padding: 10px; border-bottom: 1px solid #eee;\">{$this->escape($projectName ?? 'Untitled')}</td>
                </tr>
                <tr>
                    <td style=\"padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;\">Proposed Role</td>
                    <td style=\"padding: 10px; border-bottom: 1px solid #eee;\">{$this->escape($role)}</td>
                </tr>
            </table>",
            $invitationsUrl,
            'Review Invitation',
            $settingsUrl,
        );
    }

    private function renderInvitationApprovedEmail(string $firstName, ?string $projectName, ?array $data, string $settingsUrl): string
    {
        $acceptUrl = isset($data['token']) ? $this->urlGenerator->generate(
            'app_invitation_accept',
            ['token' => $data['token']],
            UrlGeneratorInterface::ABSOLUTE_URL,
        ) : null;

        $invitedBy = $data['invitedBy'] ?? 'A project manager';
        $role = $data['role'] ?? 'project member';

        return $this->renderEmailTemplate(
            'Project Invitation Approved',
            $firstName,
            "<p>Your invitation to join the project <strong>{$this->escape($projectName ?? 'Untitled')}</strong> has been approved by an administrator.</p>
            <p><strong>Invited by:</strong> {$this->escape($invitedBy)}</p>
            <p><strong>Your role:</strong> {$this->escape($role)}</p>
            <p>Click the button below to accept the invitation and join the project.</p>",
            $acceptUrl,
            'Accept Invitation',
            $settingsUrl,
        );
    }

    private function renderEmailTemplate(
        string $title,
        string $firstName,
        string $content,
        ?string $actionUrl,
        ?string $actionText,
        string $settingsUrl,
    ): string {
        $actionButton = '';
        if ($actionUrl !== null && $actionText !== null) {
            $actionButton = <<<HTML
            <p style="margin: 30px 0;">
                <a href="{$this->escape($actionUrl)}"
                   style="background-color: #2563eb; color: white; padding: 12px 24px;
                          text-decoration: none; border-radius: 6px; display: inline-block;">
                    {$this->escape($actionText)}
                </a>
            </p>
HTML;
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{$this->escape($title)}</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h1 style="color: #2563eb;">{$this->escape($title)}</h1>
        <p>Hi {$this->escape($firstName)},</p>
        {$content}
        {$actionButton}
        <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">
        <p style="color: #666; font-size: 12px;">
            This is an automated notification from Honeyguide Projects.
            <a href="{$this->escape($settingsUrl)}" style="color: #2563eb;">Manage notification preferences</a>
        </p>
    </div>
</body>
</html>
HTML;
    }

    private function getTaskUrl(?array $data): ?string
    {
        if (!isset($data['projectId']) || !isset($data['taskId'])) {
            return null;
        }
        return $this->urlGenerator->generate(
            'app_project_show',
            ['id' => $data['projectId'], 'task' => $data['taskId']],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }

    private function getProjectUrl(?array $data): ?string
    {
        if (!isset($data['projectId'])) {
            return null;
        }
        return $this->urlGenerator->generate(
            'app_project_show',
            ['id' => $data['projectId']],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
