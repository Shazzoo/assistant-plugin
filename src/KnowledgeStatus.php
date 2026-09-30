<?php

namespace Shazzoo\Assistant;

/**
 * Bepaalt of de assistent antwoordt, alleen antwoordt binnen geldig_tot, of doorverbindt.
 */
enum KnowledgeStatus: string
{
    case Free = 'vrij';
    case Conditional = 'voorwaarde';
    case Never = 'nooit';
}
