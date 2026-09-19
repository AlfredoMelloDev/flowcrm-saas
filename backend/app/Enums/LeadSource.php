<?php

namespace App\Enums;

enum LeadSource: string
{
    case Website = 'website';
    case Referral = 'referral';
    case SocialMedia = 'social_media';
    case Email = 'email';
    case Phone = 'phone';
    case Advertisement = 'advertisement';
    case Event = 'event';
    case Outbound = 'outbound';
    case Other = 'other';
}
