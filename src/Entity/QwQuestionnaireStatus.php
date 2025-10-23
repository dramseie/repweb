<?php
namespace App\Entity;

enum QwQuestionnaireStatus: string {
    case Draft = 'draft';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Archived = 'archived';
}
