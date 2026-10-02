<?php

declare(strict_types=1);

interface BookingConfirmedListener
{
    public function onBookingConfirmed(Booking $booking, float $total): void;
}

final class EmailConfirmationListener implements BookingConfirmedListener
{
    public function __construct(private EmailService $email = new EmailService())
    {
    }

    public function onBookingConfirmed(Booking $booking, float $total): void
    {
        $this->email->sendConfirmation($booking->customer->email, $booking->id);
    }
}

final class LoyaltyPointsListener implements BookingConfirmedListener
{
    public function __construct(private LoyaltyService $loyalty = new LoyaltyService())
    {
    }

    public function onBookingConfirmed(Booking $booking, float $total): void
    {
        $this->loyalty->addPoints($booking->customer->id, (int) floor($total));
    }
}

final class AnalyticsListener implements BookingConfirmedListener
{
    public function __construct(private AnalyticsClient $analytics = new AnalyticsClient())
    {
    }

    public function onBookingConfirmed(Booking $booking, float $total): void
    {
        $this->analytics->track('booking_confirmed', [
            'booking_id'  => $booking->id,
            'customer_id' => $booking->customer->id,
            'total'       => round($total, 2),
        ]);
    }
}

final class SmsConfirmationListener implements BookingConfirmedListener
{
    public function __construct(private SmsClient $sms = new SmsClient())
    {
    }

    public function onBookingConfirmed(Booking $booking, float $total): void
    {
        $phone = $booking->customer->phone;

        if ($phone === null || trim($phone) === '') {
            return;
        }

        $this->sms->send($phone, "Réservation {$booking->id} confirmée");
    }
}