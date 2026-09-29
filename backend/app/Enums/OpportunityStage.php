<?php

namespace App\Enums;

enum OpportunityStage: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Proposal = 'proposal';
    case Negotiation = 'negotiation';
    case Won = 'won';
    case Lost = 'lost';
}
