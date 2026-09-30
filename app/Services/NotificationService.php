<?php // app/Services/NotificationService.php
namespace App\Services;
use App\Models\{AppNotification, ProductCategory};

class NotificationService {
    public function notifyCategoryIncharges(?int $categoryId, string $type, string $title, string $body, string $linkType, int $linkId): void
    {
        if (!$categoryId) return;
        $userIds = ProductCategory::find($categoryId)?->incharges()->pluck('user_id') ?? collect();
        foreach ($userIds as $userId) {
            AppNotification::create(['user_id' => $userId, 'type' => $type, 'title' => $title, 'body' => $body, 'link_type' => $linkType, 'link_id' => $linkId]);
        }
    }
}