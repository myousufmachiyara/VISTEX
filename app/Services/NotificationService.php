<?php // app/Services/NotificationService.php
namespace App\Services;

use App\Models\{AppNotification, ProductCategory, User};

class NotificationService
{
    public function notifyCategoryIncharges(?int $categoryId, string $type, string $title, string $body, string $linkType, int $linkId): void
    {
        if (!$categoryId) return;
        $userIds = ProductCategory::find($categoryId)?->incharges()->pluck('user_id') ?? collect();
        $this->notifyUsers($userIds->all(), $type, $title, $body, $linkType, $linkId);
    }

    public function notifyRole(string $role, string $type, string $title, string $body, string $linkType, int $linkId): void
    {
        $userIds = User::role($role)->pluck('id')->all();
        $this->notifyUsers($userIds, $type, $title, $body, $linkType, $linkId);
    }

    public function notifyUsers(array $userIds, string $type, string $title, string $body, string $linkType, int $linkId): void
    {
        foreach (array_unique(array_filter($userIds)) as $userId) {
            AppNotification::create([
                'user_id' => $userId, 'type' => $type, 'title' => $title, 'body' => $body,
                'link_type' => $linkType, 'link_id' => $linkId,
            ]);
        }
    }
}
