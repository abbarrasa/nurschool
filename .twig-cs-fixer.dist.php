<?php

declare(strict_types=1);

use TwigCsFixer\Config\Config;
use TwigCsFixer\File\Finder;
use TwigCsFixer\Ruleset\Ruleset;
use TwigCsFixer\Standard\Twig;

return (new Config())
    // Relative filenames let Twiggy map diagnostics back to the host workspace.
    ->setFinder((new Finder())->in('templates'))
    ->setRuleset((new Ruleset())->addStandard(new Twig()));
