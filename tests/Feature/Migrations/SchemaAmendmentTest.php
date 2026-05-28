<?php

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

test('users role column is string type', function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $sql = DB::select("SELECT sql FROM sqlite_master WHERE type='table' AND name='users'")[0]->sql;
        expect($sql)->not->toContain('"role" in (');
    } else {
        expect(Schema::getColumnType('users', 'role'))->not->toBe('enum');
    }
});

test('users status column is string type', function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $sql = DB::select("SELECT sql FROM sqlite_master WHERE type='table' AND name='users'")[0]->sql;
        expect($sql)->not->toContain('"status" in (');
    } else {
        expect(Schema::getColumnType('users', 'status'))->not->toBe('enum');
    }
});

test('timetables day_of_week remains enum', function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $sql = DB::select("SELECT sql FROM sqlite_master WHERE type='table' AND name='timetables'")[0]->sql;
        expect($sql)->toContain('"day_of_week" in (');
    } else {
        expect(Schema::getColumnType('timetables', 'day_of_week'))->toBe('enum');
    }
});
