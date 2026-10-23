<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ComplaintResource;
use App\Filament\Resources\EnquiryResource;
use App\Filament\Resources\MoveReportResource;
use App\Filament\Resources\NewsCommentResource;
use App\Models\Complaint;
use App\Models\Enquiry;
use App\Models\MoveReport;
use App\Models\NewsComment;
use Filament\Widgets\Widget;

/** Greeting + the four things that need attention, as one-tap shortcuts. */
class WelcomeBanner extends Widget
{
    protected static string $view = 'filament.widgets.welcome-banner';

    protected static ?int $sort = -10;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $h = (int) now('Asia/Dubai')->format('G');
        $greeting = $h < 12 ? 'Good morning' : ($h < 18 ? 'Good afternoon' : 'Good evening');
        $name = trim(explode(' ', (string) (auth()->user()->name ?? 'there'))[0]);
        $dubai = now('Asia/Dubai');

        $onlyEnquiries = auth()->user()?->isEnquiriesOnly() ?? false;
        $enquiryLink = ['label' => 'New enquiries', 'count' => Enquiry::where('status', 'new')->count(), 'url' => EnquiryResource::getUrl('index'), 'hot' => true];

        return [
            'greeting' => $greeting,
            'name' => $name ?: 'there',
            'day' => $dubai->format('d'),
            'month' => $dubai->format('F Y'),
            'weekday' => $dubai->format('l'),
            'onlyEnquiries' => $onlyEnquiries,
            'links' => $onlyEnquiries ? [$enquiryLink] : [
                $enquiryLink,
                ['label' => 'Open complaints', 'count' => Complaint::whereNotIn('status', ['resolved', 'closed'])->count(), 'url' => ComplaintResource::getUrl('index'), 'hot' => false],
                ['label' => 'Move in/out today', 'count' => MoveReport::whereDate('created_at', today())->count(), 'url' => MoveReportResource::getUrl('index'), 'hot' => false],
                ['label' => 'Comments to approve', 'count' => NewsComment::where('status', 'pending')->count(), 'url' => NewsCommentResource::getUrl('index'), 'hot' => false],
            ],
        ];
    }
}
