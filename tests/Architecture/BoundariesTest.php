<?php

/*
|--------------------------------------------------------------------------
| Module boundary rules
|--------------------------------------------------------------------------
|
| A module may only depend on another module through contracts published in
| App\Shared\Domain (see docs/adr/0003-shared-kernel-contracts.md). Reaching
| into another module's internals defeats the vertical-slice boundary that
| keeps modules independently replaceable.
|
*/

$modules = ['Auction', 'User', 'Notification', 'Dashboard', 'Auth'];

foreach ($modules as $module) {
    $otherModules = array_map(
        fn (string $other): string => "App\\Modules\\{$other}",
        array_values(array_diff($modules, [$module])),
    );

    arch("module {$module} does not depend on other modules' internals")
        ->expect("App\\Modules\\{$module}")
        ->not->toUse($otherModules);

    arch("module {$module}'s domain layer has no framework dependency")
        ->expect("App\\Modules\\{$module}\\Domain")
        ->not->toUse(['Illuminate', 'Illuminate\Support\Facades']);

    arch("module {$module}'s domain layer does not throw generic exceptions")
        ->expect("App\\Modules\\{$module}\\Domain")
        ->not->toUse(['Exception', 'RuntimeException']);
}

arch('shared kernel domain layer has no framework dependency')
    ->expect('App\Shared\Domain')
    ->not->toUse(['Illuminate', 'Illuminate\Support\Facades']);

arch('shared kernel does not depend on any module')
    ->expect('App\Shared')
    ->not->toUse('App\Modules');

arch('shared kernel domain layer does not throw generic exceptions')
    ->expect('App\Shared\Domain')
    ->not->toUse(['Exception', 'RuntimeException']);
