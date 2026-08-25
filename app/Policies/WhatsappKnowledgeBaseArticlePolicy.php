<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\WhatsappKnowledgeBaseArticle;
use Illuminate\Auth\Access\HandlesAuthorization;

class WhatsappKnowledgeBaseArticlePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:WhatsappKnowledgeBaseArticle');
    }

    public function view(AuthUser $authUser, WhatsappKnowledgeBaseArticle $whatsappKnowledgeBaseArticle): bool
    {
        return $authUser->can('View:WhatsappKnowledgeBaseArticle');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:WhatsappKnowledgeBaseArticle');
    }

    public function update(AuthUser $authUser, WhatsappKnowledgeBaseArticle $whatsappKnowledgeBaseArticle): bool
    {
        return $authUser->can('Update:WhatsappKnowledgeBaseArticle');
    }

    public function delete(AuthUser $authUser, WhatsappKnowledgeBaseArticle $whatsappKnowledgeBaseArticle): bool
    {
        return $authUser->can('Delete:WhatsappKnowledgeBaseArticle');
    }

    public function restore(AuthUser $authUser, WhatsappKnowledgeBaseArticle $whatsappKnowledgeBaseArticle): bool
    {
        return $authUser->can('Restore:WhatsappKnowledgeBaseArticle');
    }

    public function forceDelete(AuthUser $authUser, WhatsappKnowledgeBaseArticle $whatsappKnowledgeBaseArticle): bool
    {
        return $authUser->can('ForceDelete:WhatsappKnowledgeBaseArticle');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:WhatsappKnowledgeBaseArticle');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:WhatsappKnowledgeBaseArticle');
    }

    public function replicate(AuthUser $authUser, WhatsappKnowledgeBaseArticle $whatsappKnowledgeBaseArticle): bool
    {
        return $authUser->can('Replicate:WhatsappKnowledgeBaseArticle');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:WhatsappKnowledgeBaseArticle');
    }
}
