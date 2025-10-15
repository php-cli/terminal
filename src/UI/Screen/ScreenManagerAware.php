<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Screen;

interface ScreenManagerAware
{
    public function setScreenManager(ScreenManager $screenManager): void;
}
