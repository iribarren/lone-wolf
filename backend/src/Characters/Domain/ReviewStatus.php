<?php

declare(strict_types=1);

namespace App\Characters\Domain;

enum ReviewStatus: string
{
    case Clean = 'clean';
    case FlaggedForReview = 'flagged_for_review';
}
