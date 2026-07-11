<?php

use App\Enums\ProcessingStage;

/**
 * Guards factual claims made in AGENTS.md / .agents/*.md against silent drift.
 * When one of these fails, the codebase changed out from under a doc — fix the
 * doc (and the claim's enforcement note) in the same diff as the code change.
 */
function repoPath(string $relative): string
{
    return dirname(__DIR__, 2).'/'.$relative;
}

function appPhpFiles(): Generator
{
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(repoPath('app')));
    foreach ($it as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            yield $file->getPathname();
        }
    }
}

// .agents/frontend.md: STAGES in Show.jsx must match ProcessingStage exactly.
it('keeps Show.jsx STAGES in sync with the ProcessingStage enum', function () {
    $jsx = file_get_contents(repoPath('resources/js/Pages/Meeting/Show.jsx'));

    foreach (ProcessingStage::cases() as $stage) {
        expect($jsx)->toContain("'{$stage->value}'");
    }
});

// .agents/pipeline.md: the Gemini model is configured once in config/services.php.
it('has no hardcoded Gemini model string outside config', function () {
    foreach (appPhpFiles() as $path) {
        $this->assertStringNotContainsString("'gemini-", file_get_contents($path),
            "Hardcoded Gemini model in {$path} — use config('services.gemini.model').");
    }
});

// AGENTS.md commands: reverb:start is intentionally NOT part of composer dev.
it('still excludes reverb from the composer dev script', function () {
    $composer = json_decode(file_get_contents(repoPath('composer.json')), true);

    expect(json_encode($composer['scripts']['dev']))->not->toContain('reverb');
});

// AGENTS.md "Status of enforcement": update that section if static analysis arrives.
it('matches the enforcement claims about static analysis tooling', function () {
    $composer = json_decode(file_get_contents(repoPath('composer.json')), true);
    $packages = array_keys(array_merge($composer['require'] ?? [], $composer['require-dev'] ?? []));

    $analysers = array_filter($packages, fn ($p) => str_contains($p, 'phpstan') || str_contains($p, 'larastan'));

    expect($analysers)->toBeEmpty();
});
