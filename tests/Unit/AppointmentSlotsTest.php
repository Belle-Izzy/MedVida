<?php

namespace Tests\Unit;

use App\Support\AppointmentSlots;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class AppointmentSlotsTest extends TestCase
{
    public function test_only_full_hour_slots_starting_at_the_schedule_start_are_valid(): void
    {
        $this->assertTrue(AppointmentSlots::fitsSchedule('07:00:00', '09:00:00', '07:00'));
        $this->assertTrue(AppointmentSlots::fitsSchedule('07:00:00', '09:00:00', '08:00'));
        $this->assertFalse(AppointmentSlots::fitsSchedule('07:00:00', '09:00:00', '07:30'));
        $this->assertFalse(AppointmentSlots::fitsSchedule('07:00:00', '09:00:00', '09:00'));
        $this->assertFalse(AppointmentSlots::fitsSchedule('07:30:00', '09:00:00', '08:30'));
    }

    public function test_past_time_today_is_not_bookable(): void
    {
        Carbon::setTestNow('2026-10-08 08:00:00');
        try {
            $this->assertFalse(AppointmentSlots::isFuture('2026-10-08', '07:00'));
            $this->assertTrue(AppointmentSlots::isFuture('2026-10-08', '09:00'));
            $this->assertTrue(AppointmentSlots::isFuture('2026-10-09', '07:00'));
        } finally {
            Carbon::setTestNow();
        }
    }
}
