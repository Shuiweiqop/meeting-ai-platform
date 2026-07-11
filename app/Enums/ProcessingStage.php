<?php

namespace App\Enums;

/**
 * Shared vocabulary with STAGES in resources/js/Pages/Meeting/Show.jsx —
 * there is no compile-time link between PHP and JS, so
 * tests/Unit/AgentsDocGuardTest.php asserts every case value appears there.
 */
enum ProcessingStage: string
{
    case ExtractingAudio = 'extracting_audio';
    case Transcribing = 'transcribing';
    case MappingSpeakers = 'mapping_speakers';
    case Summarizing = 'summarizing';
}
