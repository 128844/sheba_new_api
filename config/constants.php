<?php

return [
    'HOTLINE' => '09639 - 444 000',
    'SERVICE_VARIABLE_TYPES' => ['Fixed' => 'Fixed', 'Options' => 'Options', 'Custom' => 'Custom'],
    'PARTNER_STATUSES' => [
        'Verified' => 'Verified',
        'Unverified' => 'Unverified',
        'Paused' => 'Paused',
        'Closed' => 'Closed',
        'Blacklisted' => 'Blacklisted',
        'Waiting' => 'Waiting',
        'Onboarded' => 'Onboarded',
        'Rejected' => 'Rejected'
    ],
    'PARTNER_STATUSES_SHOW' => [
        'Verified' => ['sheba' => 'Verified', 'partner' => 'Verified', 'customer' => 'Verified'],
        'Unverified' => ['sheba' => 'Inactive', 'partner' => 'Inactive', 'customer' => 'Inactive'],
        'Paused' => ['sheba' => 'Blocked', 'partner' => 'Blocked', 'customer' => 'Blocked'],
        'Closed' => ['sheba' => 'Closed', 'partner' => 'Closed', 'customer' => 'Closed'],
        'Blacklisted' => ['sheba' => 'Blacklisted', 'partner' => 'Blacklisted', 'customer' => 'Blacklisted'],
        'Waiting' => ['sheba' => 'Ready to verified', 'partner' => 'Ready to verified', 'customer' => 'Ready to verified'],
        'Onboarded' => ['sheba' => 'Onboarded', 'partner' => 'Onboarded', 'customer' => 'Onboarded'],
        'Rejected' => ['sheba' => 'Rejected', 'partner' => 'Rejected', 'customer' => 'Rejected']
    ],
    'PARTNER_LEVELS' => ['Starter', 'Intermediate', 'Advanced'],
    'PARTNER_TYPES' => ['USP', 'NSP', 'ESP'],
    'RESOURCE_TYPES' => ['Admin' => 'Admin', 'Operation' => 'Operation', 'Finance' => 'Finance', 'Handyman' => 'Handyman'],
    'JOB_STATUSES' => [
        'Pending' => 'Pending',
        'Not_Responded' => 'Not Responded',
        'Declined' => 'Declined',
        'Accepted' => 'Accepted',
        'Schedule_Due' => 'Schedule Due',
        'Process' => 'Process',
        'Serve_Due' => 'Serve Due',
        'Served' => 'Served',
        'Cancelled' => 'Cancelled'
    ],
    'JOB_STATUS_SEQUENCE' => [
        'Pending' => 1,
        'Declined' => 1,
        'Not Responded' => 1,
        'Cancelled' => 0,
        'Accepted' => 2,
        'Schedule Due' => 3,
        'Process' => 4,
        'Serve Due' => 5,
        'Served' => 6
    ],
    'JOB_STATUSES_SHOW' => [
        'Pending' => ['sheba' => 'Pending', 'partner' => 'Pending', 'customer' => 'Order Placed'],
        'Declined' => ['sheba' => 'Declined', 'partner' => 'Declined', 'customer' => 'Order Placed'],
        'Not Responded' => ['sheba' => 'Not Responded', 'partner' => 'Not Responded', 'customer' => 'Order Placed'],
        'Accepted' => ['sheba' => 'Accepted', 'partner' => 'Accepted', 'customer' => 'Order Confirmed'],
        'Schedule Due' => ['sheba' => 'Schedule Due', 'partner' => 'Schedule Due', 'customer' => 'Order Confirmed'],
        'Process' => ['sheba' => 'Process', 'partner' => 'Process', 'customer' => 'Service is in Process'],
        'Serve Due' => ['sheba' => 'Process', 'partner' => 'Process', 'customer' => 'Service is in Process'],
        'Served' => ['sheba' => 'Served', 'partner' => 'Served', 'customer' => 'Order Completed'],
        'Cancelled' => ['sheba' => 'Cancelled', 'partner' => 'Cancelled', 'customer' => 'Order Cancelled']
    ],
    'JOB_STATUSES_COLOR' => [
        'Pending' => ['sheba' => 'Pending', 'partner' => 'Pending', 'customer' => '#fcce54'],
        'Accepted' => ['sheba' => 'Accepted', 'partner' => 'Accepted', 'customer' => '#4ec2e7'],
        'Not Responded' => ['sheba' => 'Not Responded', 'partner' => 'Not Responded', 'customer' => '#fcce54'],
        'Schedule Due' => ['sheba' => 'Schedule Due', 'partner' => 'Schedule Due', 'customer' => '#fcce54'],
        'Process' => ['sheba' => 'Process', 'partner' => 'Process', 'customer' => '#5c9ded'],
        'Served' => ['sheba' => 'Served', 'partner' => 'Served', 'customer' => '#42cb6f'],
        'Serve Due' => ['sheba' => 'Served', 'partner' => 'Served', 'customer' => '#42cb6f'],
        'Cancelled' => ['sheba' => 'Served', 'partner' => 'Served', 'customer' => '#42cb6f'],
        'Declined' => ['sheba' => 'Served', 'partner' => 'Served', 'customer' => '#fcce54']
    ],
    'PARTNER_ORDER_STATUSES' => [
        'Open' => 'Open',
        'Process' => 'Process',
        'Closed' => 'Closed',
        'Cancelled' => 'Cancelled'
    ],
    'PARTNER_ORDER_STATUSES_SHOW' => [
        'Open' => ['sheba' => 'Open', 'partner' => 'Open', 'customer' => 'Open'],
        'Process' => ['sheba' => 'Process', 'partner' => 'Process', 'customer' => 'On Going'],
        'Closed' => ['sheba' => 'Closed', 'partner' => 'Closed', 'customer' => 'Closed'],
        'Cancelled' => ['sheba' => 'Cancelled', 'partner' => 'Cancelled', 'customer' => 'Cancelled']
    ],
    'ORDER_STATUSES' => [
        'Open' => 'Open',
        'Process' => 'Process',
        'Closed' => 'Closed',
        'Cancelled' => 'Cancelled'
    ],
    'ORDER_STATUSES_SHOW' => [
        'Open' => ['sheba' => 'Open', 'partner' => 'Open', 'customer' => 'Open'],
        'Process' => ['sheba' => 'Process', 'partner' => 'Process', 'customer' => 'On Going'],
        'Closed' => ['sheba' => 'Closed', 'partner' => 'Closed', 'customer' => 'Closed'],
        'Cancelled' => ['sheba' => 'Cancelled', 'partner' => 'Cancelled', 'customer' => 'Cancelled']
    ],
    'FLAG_STATUSES' => [
        'Open' => 'Open',
        'Acknowledged' => 'Acknowledged',
        'In_Process' => 'In Process',
        'Completed' => 'Completed',
        'Closed' => 'Closed',
        'Declined' => 'Declined',
        'Halt' => 'Halt'
    ],
    'CANCEL_REQUEST_STATUSES' => [
        'Pending' => 'Pending',
        'Approved' => 'Approved',
        'Disapproved' => 'Disapproved'
    ],
    'FLAG_TYPE' => [
        'Idea' => 'Idea',
        'Assignment' => 'Assignment',
        'Improvement' => 'Improvement',
        'Risk' => 'Risk',
        'Issue' => 'Issue'
    ],
    'FLAG_TYPE_TOOLTIP' => [
        'Idea' => 'Features to make life easier',
        'Assignment' => 'Analysis or research task',
        'Improvement' => 'Improvement of existing feature ',
        'Risk' => 'Business is having serious impact',
        'Issue' => 'Business can be effected today or tomorrow'
    ],
    'FLAG_SEVERITY_LEVELS' => [
        'Critical' => 'Critical',
        'Major' => 'Major',
        'Minor' => 'Minor',
        'Not_Define' => 'Moderate'
    ],
    'FLAG_SEVERITY_LEVEL_TOOLTIP' => [
        'Critical' => 'Need to be completed within 4 hours',
        'Major' => 'Need to be completed within 24 hours',
        'Minor' => 'Need to be completed within 3 Days',
        'Moderate' => 'Need to be completed within 7 Days'
    ],
    'PRIORITY_LEVELS' => ['Green' => 'Green', 'Amber' => 'Amber', 'Red' => 'Red'],
    'ALT_PRIORITY_LEVELS' => ['Low' => 'Low', 'Medium' => 'Medium', 'High' => 'High'],
    'JOB_PREFERRED_TIMES' => [
        '10.00 A.M. - 01.00 P.M.' => '10.00 A.M. - 01.00 P.M.',
        '01.00 P.M. - 05.00 P.M.' => '01.00 P.M. - 05.00 P.M.',
        '05.00 P.M. - 09.00 P.M.' => '05.00 P.M. - 09.00 P.M.',
        'Anytime' => 'Anytime',
    ],
    'JOB_PREFERRED_TIMES_PRIORITY' => [
        '10.00 A.M. - 01.00 P.M.' => 1,
        '01.00 P.M. - 05.00 P.M.' => 2,
        '05.00 P.M. - 09.00 P.M.' => 3,
        'Anytime' => 4,
    ],
    'JOB_START_END_TIMES' => [
        '10.00 A.M. - 01.00 P.M.' => ['10:00 AM', '1:00 PM'],
        '01.00 P.M. - 05.00 P.M.' => ['1:01 PM', '5:00 PM'],
        '05.00 P.M. - 09.00 P.M.' => ['5:01 PM', '09:00 PM'],
        'Anytime' => ['12:00 AM', '11:59 PM']
    ],
    'JOB_CI_LEVELS' => ['High' => 'High', 'Medium' => 'Medium', 'Low' => 'Low'],
    'JOB_SATISFACTION_LEVELS' => ['High' => 'High', 'Medium' => 'Medium', 'Low' => 'Low'],
    'JOB_CANCEL_REASONS' => [
        'Customer Dependency' => 'Customer Dependency',
        'Customer Management' => 'Customer Management',
        'Push Sales Attempt' => 'Push Sales Attempt',
        'Insufficient Partner' => 'Insufficient Partner',
        'Price Shock' => 'Price Shock',
        'Service Limitation' => 'Service Limitation',
        'Wrongly Create Order/ Test Order' => 'Wrongly Create Order/ Test Order',
        'Service Change' => 'Service Change'
    ],
    'JOB_CANCEL_REASONS_FROM_CUSTOMER' => [
        'Will take the service later' => 'Will take the service later',
        'Price seems high' => 'Price seems high',
        "Don't like the process" => "Don't like the process",
        'Wrong service ordered' => 'Wrong service ordered',
        'Want to change service partner' => 'Want to change service partner',
        'Other' => 'Other'
    ],
    'COMPLAIN_GROUPS' => [
        'Service Partner' => 'Service Partner',
        'Communication' => 'Communication',
        'Technical' => 'Technical'
    ],
    'COMPLAIN_CATEGORIES' => [
        'Service Partner' => ['Schedule', 'Appoint Missed', 'Billing', 'Behavior', 'Performance'],
        'Communication' => ['Callback Issue', 'Bill SMS', 'Money Receipt', 'Invoice Copy', 'Behaviour Issue', 'Wrong Information'],
        'Technical' => ['Within Warranty Period', 'After Warranty Period', 'Another Parts', 'System Bug']
    ],
    'CUSTOM_ORDER_STATUSES' => [
        'Open' => 'Open',
        'Process' => 'Process',
        'OnInspection' => 'On Inspection',
        'QuotationSent' => 'Quotation Sent',
        'ConvertedToOrder' => 'Converted To Order',
        'Cancelled' => 'Cancelled'
    ],
    'CUSTOM_ORDER_STATUSES_SHOW' => [
        'Open' => ['sheba' => 'Open', 'partner' => 'Open', 'customer' => 'Open'],
        'Process' => ['sheba' => 'Process', 'partner' => 'Process', 'customer' => 'Process'],
        'OnInspection' => ['sheba' => 'On Inspection', 'partner' => 'On Inspection', 'customer' => 'On Inspection'],
        'QuotationSent' => ['sheba' => 'Quotation Sent', 'partner' => 'Quotation Sent', 'customer' => 'Quotation Sent'],
        'ConvertedToOrder' => ['sheba' => 'Converted To Order', 'partner' => 'Converted To Order', 'customer' => 'Converted To Order'],
        'Cancelled' => ['sheba' => 'Cancelled', 'partner' => 'Cancelled', 'customer' => 'Cancelled']
    ],
    'CUSTOM_ORDER_CANCEL_REASONS' => [
        'CustomerDependency' => 'Customer Dependency',
        'CustomerManagement' => 'Customer Management',
        'PushSalesAttempt' => 'Push Sales Attempt',
        'InsufficientPartner' => 'Insufficient Partner',
        'PriceShock' => 'Price Shock',
        'ServiceLimitation' => 'Service Limitation',
        'WronglyCreateOrderTestOrder' => 'Wrongly Create Order/ Test Order',
        'ServiceChange' => 'Service Change'
    ],
    'NOTIFICATION_TYPES' => [
        'Info' => 'Info',
        'Warning' => 'Warning',
        'Danger' => 'Danger',
        'Success' => 'Success'
    ],
    'SALES_CHANNELS' => [
        'Call-Center' => [
            'name' => 'Call-Center',
            'short_name' => 'CC',
            'prefix' => 'D',
            'department' => 'SD'
        ],
        'Web' => [
            'name' => 'Web',
            'short_name' => 'CC',
            'prefix' => 'D',
            'department' => 'SD'
        ],
        'App' => [
            'name' => 'App',
            'short_name' => 'CC',
            'prefix' => 'D',
            'department' => 'SD'
        ],
        'App-iOS' => [
            'name' => 'App-iOS',
            'short_name' => 'CC',
            'prefix' => 'D',
            'department' => 'SD'
        ],
        'Facebook' => [
            'name' => 'Facebook',
            'short_name' => 'CC',
            'prefix' => 'D',
            'department' => 'SD'
        ],
        'B2B' => [
            'name' => 'B2B',
            'short_name' => 'BC',
            'prefix' => 'F',
            'department' => 'FM'
        ],
        'Store' => [
            'name' => 'Store',
            'short_name' => 'DC',
            'prefix' => 'S',
            'department' => 'SM'
        ],
        'Alternative' => [
            'name' => 'Alternative',
            'short_name' => 'AC',
            'prefix' => 'A',
            'department' => 'AC'
        ],
        'Affiliation' => [
            'name' => 'Affiliation',
            'short_name' => 'AC',
            'prefix' => 'A',
            'department' => 'AC'
        ],
    ],
    'SERVICE_UNITS' => ['ft', 'sft', 'hour', 'kg', 'meal', 'person', 'piece', 'rft', 'seat', 'strip', 'km'],
    'FEEDBACK_STATUSES' => [
        'Open' => 'Open',
        'Acknowledged' => 'Acknowledged',
        'In_Process' => 'In Process',
        'Closed' => 'Closed',
        'Declined' => 'Declined',
        'Halt' => 'Halt'
    ],
    'FEEDBACK_TYPES' => [
        'Issue' => 'Issue',
        'Idea' => 'Idea',
        'Improvement' => 'Improvement'
    ],
    'BUSINESS_TYPES' => [
        'Company' => 'Company',
        'Organization' => 'Organization',
        'Institution' => 'Institution',
    ],
    "BUSINESS_MEMBER_TYPES" => [
        'Admin' => 'Admin',
        'Manager' => 'Manager',
        'Editor' => 'Editor',
        'Employee' => 'Employee'
    ],
    'JOIN_REQUEST_STATUSES' => [
        'Open' => 'Open',
        'Pending' => 'Pending',
        'Process' => 'Process',
        'Accepted' => 'Accepted',
        'Rejected' => 'Rejected'
    ],
    'COMPLAIN_SOURCE' => [
        'Direct' => 'Direct',
        'QA' => 'QA',
        'FB' => 'FB',
    ],
    'COMPLAIN_SEVERITY_LEVELS' => [
        'Low' => 'Low',
        'Medium' => 'Medium',
        'High' => 'High',
    ],
    'REFERRAL_VALID_DAYS' => 90,
    'AVATAR' => [
        env('SHEBA_CUSTOMER_APP') => 'customer',
        env('SHEBA_AFFILIATION_APP') => 'affiliate',
        env('SHEBA_RESOURCE_APP') => 'resource',
        env('SHEBA_MANGER_APP') => 'resource',
    ],
    'MANAGER' => [
        'Owner', 'Management', 'Admin', 'Operation', 'Finance'
    ],
    'FROM' => [
        'resource-app',
        'customer-app',
        'affiliation-app',
        'manager-app',
        'manager-web'
    ],
    'PARTNER_ACQUISITION_CHANNEL' => [
        'PM' => 'PM',
        'Web' => 'Web',
        'App' => 'App'
    ],
    'AFFILIATION_REWARD_MONEY' => 10,
    'AFFILIATION_REGISTRATION_BONUS' => 8,
    'AFFILIATION_ACQUISITION_MONEY' => 2,
    'API_RESPONSE_CODES' => [
        200 => ['message' => 'Successful', 'code' => 200],
        400 => ['message' => 'Bad request', 'code' => 400],
        401 => ['message' => 'Unauthorized', 'code' => 401],
        403 => ['message' => 'Forbidden', 'code' => 403],
        404 => ['message' => 'Not found', 'code' => 404],
        409 => ['message' => 'Conflict', 'code' => 409],
        422 => ['message' => 'Unprocessable Entity', 'code' => 422],
        500 => ['message' => 'Internal Server Error', 'code' => 500],
    ],
    'TRENDING' => [875, 775, 783, 629, 118, 76, 756],
    'APP_VOUCHER' => env('APP_VOUCHER'),
    'PARTNER_WITHDRAWAL_REQUEST_STATUSES' => [
        'pending'           => 'Pending',
        'approval_pending'  => 'Approval Pending',
        'approved'          => 'Approved',
        'rejected'          => 'Rejected',
        'completed'         => 'Completed',
        'failed'            => 'Failed',
        'expired'           => 'Expired',
        'cancelled'         => 'Cancelled'
    ],
    'REFERRAL_GIFT_AMOUNT' => env('REFERRAL_GIFT_AMOUNT'),
    'send_push_notifications' => env('SHEBA_SEND_PUSH_NOTIFICATIONS'),
    'APPS' => [
        "customer_app" => "https://play.google.com/store/apps/details?id=xyz.sheba.customersapp",
        "bondhu_app" => "https://play.google.com/store/apps/details?id=xyz.sheba.bondhu",
        "resource_app" => "https://play.google.com/store/apps/details?id=xyz.sheba.resource",
        "manager_app" => "https://play.google.com/store/apps/details?id=xyz.sheba.managerapp"
    ],
    'MANAGER_TOPIC_NAME' => env('MANAGER_TOPIC_NAME'),
    'PARTNER_AFFILIATIONS_STATUSES' => [
        'pending' => 'Pending',
        'rejected' => 'Rejected',
        'successful' => 'Successful'
    ],
    'PARTNER_AFFILIATIONS_REJECT_REASONS' => [
        'fake' => 'False Reference',
        'no_response' => 'No Response',
        'not_interested' => 'Not Interested',
        'not_capable' => 'Not Capable',
        'service_unavailable' => 'Service Unavailable'
    ],
    'PARTNER_AFFILIATIONS_FAKE_REJECT_REASONS' => ['fake'],
    'PARTNER_AFFILIATION_REWARD' => 200,
    'PARTNER_AFFILIATION_PARTNER_ORDER_BENCHMARK' => 2,
    'PARTNER_AFFILIATION_REWARD_BREAKDOWN' => [
        //'on_boarded' => 20,
        'waiting' => 0,
        'verified' => 30,
        'order_completed' => 70
    ],
    'PARTNER_AFFILIATION_AMBASSADOR_COMMISSION' => 30,
    'COMPLAIN_STATUSES' => [
        'Open' => 'Open',
        'Observation' => 'Observation',
        'Resolved' => 'Resolved'
    ],
    'COMPLAIN_RESOLVE_CATEGORIES' => [
        'service_provided' => 'Service Provided',
        'sp_compensated' => 'SP Compensated',
        'promo_provided' => 'Promo Code Provided',
        'development' => 'Development'
    ],
    'REWARD_TARGET_TYPE' => [
        'Partner' => 'App\Models\Partner',
        'Customer' => 'App\Models\Customer'
    ],
    'REWARD_DETAIL_TYPE' => [
        'Campaign' => 'App\Models\RewardCampaign',
        'Action' => 'App\Models\RewardAction'
    ],
    'REWARD_TYPE' => [
        'Cash' => 'Cash',
        'Point' => 'Point'
    ],
    'CAMPAIGN_REWARD_TIMELINE_TYPE' => [
        'Onetime' => 'Onetime',
        'Recurring' => 'Recurring'
    ],
    'REWARD_CONSTRAINTS' => [
        'category' => 'App\Models\Category',
        'partner_package' => 'App\Models\PartnerSubscriptionPackage'
    ],
    'PARTNER_PACKAGE_UPDATE_STATUSES' => [
        'Pending' => 'Pending',
        'Approved' => 'Approved',
        'Rejected' => 'Rejected'
    ],
    'JOB_ON_PREMISE' => [
        'customer' => 'customer',
        'partner' => 'partner'
    ],
    'PARTNER_SERVICE_UPDATE_STATUS' => [
        'Pending' => 'Pending',
        'Approved' => 'Approved',
        'Rejected' => 'Rejected'
    ],
    'WITHDRAW_LIMIT' => [
        'bkash' => ['min' => 500, 'max' => 10000],
        'bank' => ['min' => 10000, 'max' => 1000000]
    ]
];