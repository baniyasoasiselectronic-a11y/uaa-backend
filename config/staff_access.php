<?php

/*
 * Which staff role sees which admin screen.
 *
 * limit_screens = false (default): nothing changes except that the Users screen is super_admin only.
 * limit_screens = true  (set STAFF_SCREEN_LIMITS=true in .env): each role sees ONLY the screens listed here.
 *   - super_admin always sees everything and is not listed.
 *   - A screen that is missing from this list is visible to super_admin only.
 *   - UserResource is never listed: accounts and roles are super_admin only in both modes.
 * Review this list with the team before switching limit_screens on; change it here, no other code is involved.
 */
return [
    'limit_screens' => env('STAFF_SCREEN_LIMITS', false),

    'resources' => [
        // buildings, listings, projects
        'BuildingResource'         => ['property_manager', 'content_manager'],
        'PropertyResource'         => ['property_manager', 'sales_agent', 'content_manager'],
        'UnitResource'             => ['property_manager', 'sales_agent'],
        'UpcomingProjectResource'  => ['property_manager', 'sales_agent', 'content_manager'],
        'CommunityResource'        => ['property_manager', 'content_manager'],
        'NeighborhoodResource'     => ['property_manager', 'content_manager'],
        'PropertyTypeResource'     => ['property_manager', 'content_manager'],
        'AmenityResource'          => ['property_manager', 'content_manager'],
        'OpenHouseResource'        => ['property_manager', 'sales_agent'],

        // customers / sales
        'LeadResource'             => ['sales_agent', 'property_manager'],
        'EnquiryResource'          => ['sales_agent', 'property_manager'],
        'CallbackRequestResource'  => ['sales_agent', 'property_manager'],
        'ContactRequestResource'   => ['sales_agent', 'property_manager'],
        'ListWithUsRequestResource' => ['sales_agent', 'property_manager'],

        // tenants: complaints, maintenance, inspections, renewals
        'ComplaintResource'        => ['maintenance', 'property_manager'],
        'MoveReportResource'       => ['maintenance', 'property_manager'],
        'TechnicianReportResource' => ['maintenance', 'property_manager'],
        'ContractRenewalResource'  => ['property_manager'],

        // money: super_admin only (not listed): PaymentResource

        // website content
        'NewsResource'             => ['content_manager'],
        'NewsCategoryResource'     => ['content_manager'],
        'NewsCommentResource'      => ['content_manager'],

        // people / jobs
        'CareerResource'           => ['hr'],
        'JobApplicationResource'   => ['hr'],
    ],
];
