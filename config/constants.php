<?php
return [
    "BLOG_URL" => env('BLOG_URL'),
    'BANK_LOAN_PDF_TYPES' => [
        'SanctionLetter' => 'sanctionLetter',
        'Application' => 'application',
        'ProposalLetter' => 'proposalLetter'
    ],
    'PARTNER_SHOWABLE_PACKAGE' => [
        1,
        2,
        3,
        4
    ],
    'smanager_logo' => 'https://cdn-shebaxyz.s3.ap-south-1.amazonaws.com/partner_assets/assets/images/logo/smanager-44-44-px.png',
    'AFFILIATE_VIDEO_LINK' => '',
    'STARTING_YEAR' => '2017',
    'HOTLINE' => '09639 - 444 000',
    'SERVICE_VARIABLE_TYPES' => [
        'Fixed' => 'Fixed',
        'Options' => 'Options',
        'Custom' => 'Custom'
    ],
    'PARTNER_STATUSES' => [
        'Verified' => 'Verified',
        'Unverified' => 'Unverified',
        'Paused' => 'Paused',
        'Closed' => 'Closed',
        'Blacklisted' => 'Blacklisted',
        'Waiting' => 'Waiting',
        'Onboarded' => 'Onboarded',
        'Rejected' => 'Rejected',
        'Inactive' => 'Inactive'
    ],
    'PARTNER_STATUSES_SHOW' => [
        'Verified' => [
            'sheba' => 'Verified',
            'partner' => 'Active',
            'customer' => 'Verified'
        ],
        'Unverified' => [
            'sheba' => 'Inactive',
            'partner' => 'Inactive',
            'customer' => 'Inactive'
        ],
        'Paused' => [
            'sheba' => 'Blocked',
            'partner' => 'Inactive',
            'customer' => 'Blocked'
        ],
        'Closed' => [
            'sheba' => 'Closed',
            'partner' => 'Closed',
            'customer' => 'Closed'
        ],
        'Blacklisted' => [
            'sheba' => 'Blacklisted',
            'partner' => 'Blacklisted',
            'customer' => 'Blacklisted'
        ],
        'Waiting' => [
            'sheba' => 'Ready to verified',
            'partner' => 'Inactive',
            'customer' => 'Ready to verified'
        ],
        'Onboarded' => [
            'sheba' => 'Onboarded',
            'partner' => 'Inactive',
            'customer' => 'Onboarded'
        ],
        'Rejected' => [
            'sheba' => 'Rejected',
            'partner' => 'Inactive',
            'customer' => 'Rejected'
        ],
        'Inactive' => [
            'sheba' => 'Inactive',
            'partner' => 'Inactive',
            'customer' => 'Inactive'
        ]
    ],
    'PARTNER_LEVELS' => [
        'Starter',
        'Intermediate',
        'Advanced'
    ],
    'PARTNER_TYPES' => [
        'USP',
        'NSP',
        'ESP'
    ],
    'RESOURCE_TYPES' => [
        'Admin' => 'Admin',
        'Operation' => 'Operation',
        'Finance' => 'Finance',
        'Handyman' => 'Handyman',
        'Salesman' => 'Salesman',
        'Owner' => 'Owner'
    ],
    'JOB_STATUSES' => [
        'Pending' => 'Pending',
        'Not_Responded' => 'Not Responded',
        'Declined' => 'Declined',
        'Accepted' => 'Accepted',
        'Schedule_Due' => 'Schedule Due',
        'Ready_To_Pick' => 'Ready To Pick',
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
    'JOB_STATUS_SEQUENCE_FOR_ACTION' => [
        'Pending' => 1,
        'Declined' => 1,
        'Not Responded' => 1,
        'Accepted' => 2,
        'Schedule Due' => 3,
        'Process' => 4,
        'Serve Due' => 5,
        'Served' => 6,
        'Cancelled' => 7
    ],
    'JOB_STATUSES_SHOW' => [
        'Pending' => [
            'sheba' => 'Pending',
            'partner' => 'Pending',
            'customer' => 'Order Placed'
        ],
        'Declined' => [
            'sheba' => 'Declined',
            'partner' => 'Declined',
            'customer' => 'Order Placed'
        ],
        'Not Responded' => [
            'sheba' => 'Not Responded',
            'partner' => 'Not Responded',
            'customer' => 'Order Placed'
        ],
        'Accepted' => [
            'sheba' => 'Accepted',
            'partner' => 'Accepted',
            'customer' => 'Confirmed'
        ],
        'Schedule Due' => [
            'sheba' => 'Schedule Due',
            'partner' => 'Schedule Due',
            'customer' => 'Confirmed'
        ],
        'Process' => [
            'sheba' => 'Process',
            'partner' => 'Process',
            'customer' => 'In Process'
        ],
        'Serve Due' => [
            'sheba' => 'Process',
            'partner' => 'Process',
            'customer' => 'In Process'
        ],
        'Served' => [
            'sheba' => 'Served',
            'partner' => 'Served',
            'customer' => 'Completed'
        ],
        'Cancelled' => [
            'sheba' => 'Cancelled',
            'partner' => 'Cancelled',
            'customer' => 'Cancelled'
        ]
    ],
    'JOB_STATUSES_COLOR' => [
        'Pending' => [
            'sheba' => 'Pending',
            'partner' => 'Pending',
            'customer' => '#fcce54'
        ],
        'Accepted' => [
            'sheba' => 'Accepted',
            'partner' => 'Accepted',
            'customer' => '#4ec2e7'
        ],
        'Not Responded' => [
            'sheba' => 'Not Responded',
            'partner' => 'Not Responded',
            'customer' => '#fcce54'
        ],
        'Schedule Due' => [
            'sheba' => 'Schedule Due',
            'partner' => 'Schedule Due',
            'customer' => '#fcce54'
        ],
        'Process' => [
            'sheba' => 'Process',
            'partner' => 'Process',
            'customer' => '#5c9ded'
        ],
        'Served' => [
            'sheba' => 'Served',
            'partner' => 'Served',
            'customer' => '#42cb6f'
        ],
        'Serve Due' => [
            'sheba' => 'Served',
            'partner' => 'Served',
            'customer' => '#42cb6f'
        ],
        'Cancelled' => [
            'sheba' => 'Served',
            'partner' => 'Served',
            'customer' => '#42cb6f'
        ],
        'Declined' => [
            'sheba' => 'Served',
            'partner' => 'Served',
            'customer' => '#fcce54'
        ]
    ],
    'BID_STATUSES_COLOR' => [
        'pending' => '#ff8219',
        'rejected' => '#fa5252',
        'accepted' => '#12b886',
        'drafted' => '#0c99f7',
        'sent' => '#0c99f7',
        'awarded' => '#0c99f7'
    ],
    'PARTNER_ORDER_STATUSES' => [
        'Open' => 'Open',
        'Process' => 'Process',
        'Closed' => 'Closed',
        'Cancelled' => 'Cancelled'
    ],
    'PARTNER_ORDER_STATUSES_SHOW' => [
        'Open' => [
            'sheba' => 'Open',
            'partner' => 'Open',
            'customer' => 'Open'
        ],
        'Process' => [
            'sheba' => 'Process',
            'partner' => 'Process',
            'customer' => 'On Going'
        ],
        'Closed' => [
            'sheba' => 'Closed',
            'partner' => 'Closed',
            'customer' => 'Closed'
        ],
        'Cancelled' => [
            'sheba' => 'Cancelled',
            'partner' => 'Cancelled',
            'customer' => 'Cancelled'
        ]
    ],
    'ORDER_STATUSES' => [
        'Open' => 'Open',
        'Process' => 'Process',
        'Closed' => 'Closed',
        'Cancelled' => 'Cancelled'
    ],
    'ORDER_STATUSES_SHOW' => [
        'Open' => [
            'sheba' => 'Open',
            'partner' => 'Open',
            'customer' => 'Open'
        ],
        'Process' => [
            'sheba' => 'Process',
            'partner' => 'Process',
            'customer' => 'On Going'
        ],
        'Closed' => [
            'sheba' => 'Closed',
            'partner' => 'Closed',
            'customer' => 'Closed'
        ],
        'Cancelled' => [
            'sheba' => 'Cancelled',
            'partner' => 'Cancelled',
            'customer' => 'Cancelled'
        ]
    ],
    'PARTNER_MINIMUM_RESPONSE_TIME' => 30,
    'PARTNER_MAXIMUM_RESPONSE_TIME' => 120,
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
    'PRIORITY_LEVELS' => [
        'Green' => 'Green',
        'Amber' => 'Amber',
        'Red' => 'Red'
    ],
    'ALT_PRIORITY_LEVELS' => [
        'Low' => 'Low',
        'Medium' => 'Medium',
        'High' => 'High'
    ],
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
        '10.00 A.M. - 01.00 P.M.' => [
            '10:00 AM',
            '1:00 PM'
        ],
        '01.00 P.M. - 05.00 P.M.' => [
            '1:01 PM',
            '5:00 PM'
        ],
        '05.00 P.M. - 09.00 P.M.' => [
            '5:01 PM',
            '09:00 PM'
        ],
        'Anytime' => [
            '12:00 AM',
            '11:59 PM'
        ]
    ],
    'JOB_CI_LEVELS' => [
        'High' => 'High',
        'Medium' => 'Medium',
        'Low' => 'Low'
    ],
    'JOB_SATISFACTION_LEVELS' => [
        'High' => 'High',
        'Medium' => 'Medium',
        'Low' => 'Low'
    ],
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
        'Service Partner' => [
            'Schedule',
            'Appoint Missed',
            'Billing',
            'Behavior',
            'Performance'
        ],
        'Communication' => [
            'Callback Issue',
            'Bill SMS',
            'Money Receipt',
            'Invoice Copy',
            'Behaviour Issue',
            'Wrong Information'
        ],
        'Technical' => [
            'Within Warranty Period',
            'After Warranty Period',
            'Another Parts',
            'System Bug'
        ]
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
        'Open' => [
            'sheba' => 'Open',
            'partner' => 'Open',
            'customer' => 'Open'
        ],
        'Process' => [
            'sheba' => 'Process',
            'partner' => 'Process',
            'customer' => 'Process'
        ],
        'OnInspection' => [
            'sheba' => 'On Inspection',
            'partner' => 'On Inspection',
            'customer' => 'On Inspection'
        ],
        'QuotationSent' => [
            'sheba' => 'Quotation Sent',
            'partner' => 'Quotation Sent',
            'customer' => 'Quotation Sent'
        ],
        'ConvertedToOrder' => [
            'sheba' => 'Converted To Order',
            'partner' => 'Converted To Order',
            'customer' => 'Converted To Order'
        ],
        'Cancelled' => [
            'sheba' => 'Cancelled',
            'partner' => 'Cancelled',
            'customer' => 'Cancelled'
        ]
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
    'NOTIFICATION_ICONS' => [
        'Info' => 'sheba_xyz/png/notification/info.png',
        'Warning' => 'sheba_xyz/png/notification/warning.png',
        'Danger' => 'sheba_xyz/png/notification/danger.png',
        'Success' => 'sheba_xyz/png/notification/success.png',
        'Default' => 'sheba_xyz/png/notification/default.png'
    ],
    'NOTIFICATION_DEFAULTS' => [
        'banner' => 'https://s3.ap-south-1.amazonaws.com/cdn-shebaxyz/partner_assets/assets/images/home_v3/slider-img3.jpg',
        'title' => 'Notification',
        'short_description' => "Its a simple notification",
        'description' => "Its a simple notification",
        'button_text' => 'OK',
        'type' => 'Info',
        'target_link' => "HOME",
        'target_type' => '',
        'target_id' => ''
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
            'name' => 'Corporate',
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
        'Othoba' => [
            'name' => 'Othoba',
            'short_name' => 'DC',
            'prefix' => 'D',
            'department' => 'CC'
        ],
        'Daraz' => [
            'name' => 'Daraz',
            'short_name' => 'DC',
            'prefix' => 'D',
            'department' => 'CC'
        ],
        'Pickaboo' => [
            'name' => 'Pickaboo',
            'short_name' => 'DC',
            'prefix' => 'D',
            'department' => 'CC'
        ],
        'E-Shop' => [
            'name' => 'E-Shop',
            'short_name' => 'DC',
            'prefix' => 'D',
            'department' => 'CC'
        ],
        'Bondhu' => [
            'name' => 'Bondhu',
            'short_name' => 'AC',
            'prefix' => 'A',
            'department' => 'AC'
        ],
        'Telesales' => [
            'name' => 'Telesales',
            'short_name' => 'TEL',
            'prefix' => 'T',
            'department' => 'TEL'
        ],
        'DDN' => [
            'name' => 'DDN',
            'short_name' => 'DDN',
            'prefix' => 'D',
            'department' => 'AC'
        ]
    ],
    'SERVICE_UNITS' => [
        'ft',
        'sft',
        'hour',
        'kg',
        'meal',
        'person',
        'piece',
        'rft',
        'seat',
        'strip',
        'km',
        'basket',
        'Cow Price',
        'litre',
        'বান্ডেল',
        'দিন',
        'cft'
    ],
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
        'customer-portal' => 'customer',
        env('SHEBA_AFFILIATION_APP') => 'affiliate',
        env('SHEBA_RESOURCE_APP') => 'resource',
        env('SHEBA_MANGER_APP') => 'resource',
        'user' => 'user',
        'bank-loan-portal' => 'bankUser',
        'retailer-portal' => 'strategicPartnerMember'
    ],
    'MANAGER' => [
        'Owner',
        'Management',
        'Admin',
        'Operation',
        'Finance'
    ],
    'FROM' => [
        'resource-app',
        'customer-app',
        'affiliation-app',
        'manager-app',
        'employee-app',
        'manager-web',
        'business-portal',
        'bank-loan-portal',
        'customer-portal',
        'bank-loan-portal',
        'employee-app',
        'retailer-portal'
    ],
    'PARTNER_ACQUISITION_CHANNEL' => [
        'PM' => 'PM',
        'Web' => 'Web',
        'App' => 'App'
    ],
    'AFFILIATION_REWARD_MONEY' => 0,
    'AFFILIATION_REGISTRATION_BONUS' => 0,
    'AFFILIATION_ACQUISITION_MONEY' => 0,
    'API_RESPONSE_CODES' => [
        200 => [
            'message' => 'Successful',
            'code' => 200
        ],
        202 => [
            'message' => 'Successful',
            'code' => 202
        ],
        303 => [
            'message' => 'Partial Updates Successful',
            'code' => 303
        ],
        400 => [
            'message' => 'Bad request',
            'code' => 400
        ],
        401 => [
            'message' => 'Unauthorized',
            'code' => 401
        ],
        403 => [
            'message' => 'Forbidden',
            'code' => 403
        ],
        404 => [
            'message' => 'Not found',
            'code' => 404
        ],
        409 => [
            'message' => 'Conflict',
            'code' => 409
        ],
        420 => [
            'message' => 'Not Allowed',
            'code' => 420
        ],
        421 => [
            'message' => 'Misdirected.',
            'code' => 421
        ],
        422 => [
            'message' => 'Unprocessable Entity',
            'code' => 422
        ],
        500 => [
            'message' => 'Internal Server Error',
            'code' => 500
        ],
    ],
    'TRENDING' => [
        875,
        775,
        783,
        629,
        118,
        76,
        756
    ],
    'APP_VOUCHER' => env('APP_VOUCHER'),
    'PARTNER_WITHDRAWAL_REQUEST_STATUSES' => [
        'pending' => 'Pending',
        'approval_pending' => 'Approval Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'completed' => 'Completed',
        'failed' => 'Failed',
        'expired' => 'Expired',
        'cancelled' => 'Cancelled'
    ],
    'REFERRAL_GIFT_AMOUNT' => env('REFERRAL_GIFT_AMOUNT'),
    'send_push_notifications' => env('SHEBA_SEND_PUSH_NOTIFICATIONS'),
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
    'PARTNER_DEFAULT_SECURITY_MONEY' => 100,
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
        'Resolved' => 'Resolved',
        'Rejected'    => 'Rejected'
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
        'category' => 'Sheba\Dal\Category\Category',
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
        'bkash' => [
            'min' => 200,
            'max' => 15000
        ],
        'bank' => [
            'min' => 1000,
            'max' => 250000
        ]
    ],
    'DELIVERY_CHARGE_UPDATE_STATUSES' => [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected'
    ],
    'WEEK_DAYS' => [
        'Sunday',
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday'
    ],
    'PARTNER_BADGE' => [
        'silver' => 'silver',
        'gold' => 'gold'
    ],
    'MODERATOR_DISTANCE_THRESHOLD' => 100,
    'AFFILIATION_LITE_ONBOARD_REWARD' => 0,
    'AFFILIATION_LITE_ONBOARD_MODERATION_REWARD' => 0,
    'AFFILIATION_LITE_ONBOARD_AMBASSADOR_REWARD' => 0,
    'CUSTOMER_REVIEW_OPEN_DAY_LIMIT' => 14,
    'SMS_CAMPAIGN' => [
        'rate_per_sms' => 0.30
    ],
    'LITE_PARTNER_REJECT_REASON' => [
        [
            'text' => 'Fake Request',
            'value' => 'fake_request'
        ],
        [
            'text' => 'Wrong Address',
            'value' => 'wrong_address'
        ],
        [
            'text' => 'Incorrect Service Category',
            'value' => 'incorrect_service_category'
        ],
        [
            'text' => 'Invalid Service',
            'value' => 'invalid_service'
        ]
        /*[
            'text' => 'Other',
            'value' => 'other'
        ]*/
    ],
    'BANK_ACCOUNT_TYPE' => [
        [
            'key' => 'savings',
            'en' => 'savings',
            'bn' => 'সেভিংস'
        ],
        [
            'key' => 'current',
            'en' => 'current',
            'bn' => 'কারেন্ট'
        ]
    ],
    'BKASH_ACCOUNT_TYPE' => [
        [
            'key' => 'personal',
            'en' => 'personal',
            'bn' => 'পার্সোনাল'
        ],
        [
            'key' => 'agent',
            'en' => 'agent',
            'bn' => 'এজেন্ট'
        ],
        [
            'key' => 'merchant',
            'en' => 'merchant',
            'bn' => 'মার্চেন্ট'
        ]
    ],
    'SUGGESTED_OCCUPATION' => [
        [
            'key' => 'government_service',
            'en' => 'government_service',
            'bn' => 'গভর্নমেন্ট সার্ভিস '
        ],
        [
            'key' => 'private_service',
            'en' => 'private_service',
            'bn' => 'প্রাইভেট সার্ভিস'
        ],
        [
            'key' => 'business',
            'en' => 'business',
            'bn' => 'বিজনেস'
        ],
        [
            'key' => 'other',
            'en' => 'other',
            'bn' => 'অন্যান্য'
        ]
    ],
    'LOAN_STATUS' => [
        'applied' => 'applied',
        'submitted' => 'submitted',
        'verified' => 'verified',
        'approved' => 'approved',
        'sanction_issued' => 'sanction_issued',
        'disbursed' => 'disbursed',
        'closed' => 'closed',
        'rejected' => 'rejected',
        'hold' => 'hold',
        'declined' => 'declined',
        'withdrawal' => 'withdrawal',
        'considerable' => 'considerable'
    ],
    'LOAN_STATUS_BN' => [
        'applied' => [
            'bn' => 'এপ্লাইড',
            'color' => '#F2994A'
        ],
        'submitted' => [
            'bn' => 'সাবমিটেড',
            'color' => '#2D9CDB'
        ],
        'verified' => [
            'bn' => 'ভেরিফাইড',
            'color' => '#404FD3'
        ],
        'approved' => [
            'bn' => 'এপ্রুভড',
            'color' => '#9B51E0'
        ],
        'sanction_issued' => [
            'bn' => 'স্যাংশন ইস্যু',
            'color' => '#1FB3A2'
        ],
        'disbursed' => [
            'bn' => 'ডিসবার্সড',
            'color' => '#27AE60'
        ],
        'closed' => [
            'bn' => 'ক্লোজ',
            'color' => '#117746'
        ],
        'rejected' => [
            'bn' => 'রিজেক্ট',
            'color' => '#EC2020'
        ],
        'hold' => [
            'bn' => 'হোল্ড',
            'color' => '#EAB618'
        ],
        'declined' => [
            'bn' => 'ডিকলাইন',
            'color' => '#EC2020'
        ],
        'withdrawal' => [
            'bn' => 'উইথড্রয়াল',
            'color' => '#979797'
        ],
        'considerable' => [
            'bn' => 'এপ্লাইড',
            'color' => '#F2994A'
        ]
    ],
    'AVAILABLE_BANK_FOR_LOAN' => [
        'IPDC Finance' => [
            'name' => 'IPDC Finance',
            'logo' => 'https://s3.ap-south-1.amazonaws.com/cdn-shebaxyz/images/bank_icon/ipdc.png',
            'interest' => '10',
        ],
        'BRAC Bank' => [
            'name' => 'BRAC Bank',
            'logo' => 'https://s3.ap-south-1.amazonaws.com/cdn-shebaxyz/images/bank_icon/brac.png',
            'interest' => '11.5',
        ],
        /*'City Bank' => [
            'name' => 'City Bank',
            'logo' => 'https://s3.ap-south-1.amazonaws.com/cdn-shebaxyz/images/bank_icon/city.png',
            'interest' => '10.5',
        ],*/
        'BRAC' => [
            'name' => 'BRAC',
            'logo' => 'https://s3.ap-south-1.amazonaws.com/cdn-shebaxyz/images/bank_icon/brac_pink_logo.png',
            'interest' => '25',
        ],
    ],
    'LOAN_CONFIG' => [
        'interest' => 15,
        'minimum_amount' => 50000,
        'minimum_duration' => 6
    ],
    'WEEKS' => [
        'Saturday' => 1,
        'Sunday' => 2,
        'Monday' => 3,
        'Tuesday' => 4,
        'Wednesday' => 5,
        'Thursday' => 6,
        'Friday' => 7
    ],
    'FUEL_TYPES' => [
        'petrol',
        'diesel',
        'octane',
        'cng'
    ],
    'FUEL_UNITS' => [
        'ltr',
        'cubic_feet'
    ],
    'AVATAR_FROM_CLASS' => [
        env('SHEBA_CUSTOMER_APP') => 'Customer',
        env('SHEBA_AFFILIATION_APP') => 'Affiliate',
        env('SHEBA_RESOURCE_APP') => 'Resource',
        env('SHEBA_MANGER_APP') => 'Partner',
        'user' => 'Profile',
        'bank-loan-portal' => 'BankUser',
        'retailer-portal' => 'StrategicPartnerMember'
    ],
    'PARTNER_PACKAGE_CHARGE_TYPES' => [
        'Upgrade' => 'upgrade',
        'Downgrade' => 'downgrade',
        'Renewed' => 'renewed'
    ],
    'DEVELOPMENT_PLATFORMS' => [
        'android',
        'ios',
        'web',
        'all'
    ],
    'POS_SERVICE_UNITS' => [
        'ft' => [
            'bn' => 'ফুট',
            'en' => 'ft'
        ],
        'sft' => [
            'bn' => 'স্কয়ার ফিট',
            'en' => 'sft'
        ],
        'sq.m' => [
            'bn' => 'স্কয়ার মিটার',
            'en' => 'sq.m'
        ],
        'kg' => [
            'bn' => 'কেজি',
            'en' => 'kg'
        ],
        'piece' => [
            'bn' => 'পিস',
            'en' => 'piece'
        ],
        'km' => [
            'bn' => 'কিমি',
            'en' => 'km'
        ],
        'litre' => [
            'bn' => 'লিটার',
            'en' => 'litre'
        ],
        'meter' => [
            'bn' => 'মিটার',
            'en' => 'meter'
        ],
        'dozen' => [
            'bn' => 'ডজন',
            'en' => 'dozen'
        ],
        'dozon' => [
            'bn' => 'ডজন',
            'en' => 'dozen'
        ],
        'inch' => [
            'bn' => 'ইঞ্চি',
            'en' => 'inch'
        ],
        'bosta' => [
            'bn' => 'বস্তা',
            'en' => 'bosta'
        ],
        'unit' => [
            'bn' => 'টি',
            'en' => 'unit'
        ],
        'set' => [
            'bn' => 'সেট',
            'en' => 'set'
        ],
        'carton' => [
            'bn' => 'কার্টন',
            'en' => 'carton'
        ]
    ],
    'TICKET_LOG' => [
        'movie_ticket_purchase' => [
            'title' => 'Movie Ticket',
            'log' => '%s TK has been charged as Movie Ticket price'
        ],
        'transport_ticket_purchase' => [
            'title' => 'Bus Ticket',
            'log' => '%s TK has been charged as Transport Ticket price'
        ]
    ],
    'BIDDER_TYPE' => [
        'affiliate' => 'App\Models\Affiliate',
        'partner' => 'App\Models\Partner',
        'resource' => 'App\Models\Resource'
    ],
    'BID_STATUSES' => [
        'drafted' => 'drafted',
        'sent' => 'sent'
    ],
    'BID_PROCUREMENT_STATUSES' => [
        'pending' => 'pending',
        'rejected' => 'rejected',
        'accepted' => 'accepted',
        'drafted' => 'drafted'
    ],
    'BID_PROCUREMENT_ITEM_FIELD' => [
        'text' => 'text',
        'textarea' => 'textarea',
        'radio' => 'radio',
        'checkbox' => 'checkbox',
        'number' => 'number',
        'select' => 'select'
    ],
    'PROCUREMENT_ORDER_STATUSES_COLOR' => [
        'accepted' => '#FF8219',
        'started' => '#7950F2',
        'served' => '#12B886',
        'cancelled' => '#B3B7CC'
    ],
    'PROCUREMENT_PAYMENT_STATUS' => [
        'pending' => '#FF8219',
        'approved' => '#12B886',
        'acknowledged' => '#12B886',
        'rejected' => '#B3B7CC',
        'paid' => '#12B886'
    ],
    'TOPUP_BULK_REQUEST_STATUS' => [
        'pending' => 'pending',
        'completed' => 'completed',
        'successful' => 'successful',
        'failed' => 'failed'
    ],
    'AFFILIATE_REWARD' => [
        'SERVICE_REFER' => [
            'AGENT' => [
                'percentage' => 5,
                'cap' => 200
            ],
            'AMBASSADOR' => [
                'percentage' => 0,
                'cap' => 0
            ]
        ],
        'TOP_UP' => [
            'AGENT' => [
                'percentage' => 2.5,
                'cap' => 50
            ],
            'AMBASSADOR' => [
                'percentage' => 0,
                'cap' => 0
            ]
        ],
        'MOVIE' => [
            'AGENT' => [
                'percentage' => 2,
                'cap' => 50
            ],
            'AMBASSADOR' => [
                'percentage' => 0,
                'cap' => 0
            ]
        ],
        'TRANSPORT' => [
            'AGENT' => [
                'percentage' => 0,
                'cap' => 0
            ],
            'AMBASSADOR' => [
                'percentage' => 0,
                'cap' => 0
            ]
        ],
        'DDN' => [
            'AGENT' => [
                'percentage' => 5,
                'cap' => 200,
            ],
            'AMBASSADOR' => [
                'percentage' => 0,
                'cap' => 0
            ]
        ]
    ],
    'PARTNER_BUSINESS_TYPES' => [
        [
            'key' => 'manufacturing',
            'en' => 'Manufacturing',
            'bn' => 'ম্যানুফ্যাকচারিং'
        ],
        [
            'key' => 'trading',
            'en' => 'Trading',
            'bn' => 'ট্রেডিং'
        ],
        [
            'key' => 'service',
            'en' => 'Service',
            'bn' => 'সার্ভিস'
        ]
    ],
    'PARTNER_SMANAGER_BUSINESS_TYPE' => [
        [
            'key' => 'ownership',
            'en' => 'Ownership',
            'bn' => 'মালিকানা'
        ],
        [
            'key' => 'partnership',
            'en' => 'Partnership',
            'bn' => 'পার্টনারশিপ'
        ],
        [
            'key' => 'limited',
            'en' => 'Limited Company',
            'bn' => 'লিমিটেড কোম্পানি'
        ]
    ],
    'PARTNER_OWNER_TYPES' => [
        [
            'key' => 'limited',
            'en' => 'Limited',
            'bn' => 'লিমিটেড'
        ],
        [
            'key' => 'partnership',
            'en' => 'Partnership',
            'bn' => 'পার্টনারশিপ'
        ],
        [
            'key' => 'proprietorship',
            'en' => 'Proprietorship',
            'bn' => 'প্রোপ্রাইটরশিপ'
        ],
    ],
    'ownership_type_en' => [
        'লিমিটেড' => 'Limited',
        'পার্টনারশিপ' => 'Partnership',
        'প্রোপ্রাইটরশিপ' => 'Proprietorship'
    ],
    'PARTNER_BUSINESS_CATEGORIES' => [
        'Small',
        'Micro',
        'Medium'
    ],
    'PARTNER_BUSINESS_SECTORS' => [
        'Service',
        'Non Service'
    ],
    'LOAN_GROUP' => [
        'G1',
        'G2',
        'G3'
    ],
    'PARTNER_BUSINESS_TYPE' => [
        'grocery_business' => [
            'en' =>  'Grocery Business',
            'bn' =>  'মুদি ব্যবসা',
        ],
        'clothing_business' => [
            'en' =>  'Clothing Business',
            'bn' =>  'কাপড়ের ব্যবসা'
        ],
        'electronics' => [
            'en' =>  'Electronics',
            'bn' =>   'ইলেক্ট্রনিক্স'
        ],
        'mobile_and_gadgets' => [
            'en' =>  'Mobile and Gadgets',
            'bn' =>  'মোবাইল এবং গ্যাজেট'
        ],
        'e-commerce' => [
            'en' =>  'E-Commerce',
            'bn' =>  'ই-কমার্স'
        ],
        'drugstore' => [
            'en' =>  'Drugstore',
            'bn' =>  'ঔষধের দোকান'
        ],
        'm_commerce_or_mobile_topup_business' => [
            'id' => 6,
            'en' =>  'M-Commerce or Mobile Topup Business',
            'bn' =>  'এম-কমার্স বা মোবাইল টপআপ ব্যবসা'
        ],
        'f_commerce_or_facebook_business' => [
            'en' =>  'F-Commerce or Facebook Business',
            'bn' =>  'এফ-কমার্স বা ফেসবুক ব্যবসা'
        ],
        'raw_materials_business' => [
            'en' =>  'Raw Materials Business',
            'bn' =>  'কাঁচামালের ব্যবসায়'
        ],
        'poultry_and_agro_business' => [
            'en' =>  'Poultry and Agro Business',
            'bn' =>  'পোলট্রি এন্ড এগ্রো ব্যবসায়'
        ],
        'cosmetics' => [
            'en' =>  'Cosmetics',
            'bn' =>  'কসমেটিক্স'
        ],
        'confectionery_and_foods' => [
            'en' =>  'Confectionery and Foods',
            'bn' =>  'কনফেকশনারি এন্ড ফুডস'
        ],
        'hardware' => [
            'en' =>  'Hardware',
            'bn' =>  'হার্ডওয়্যার'
        ],
        'service_and_repairing' => [
            'en' =>  'Service & Repairing',
            'bn' =>  'সার্ভিস ও রিপেয়ারিং'
        ],
        'furniture' => [
            'en' =>  'Furniture',
            'bn' =>  'ফার্নিচার'
        ],
        'household_items' => [
            'en' =>  'Household Items',
            'bn' =>  'গৃহস্থালি জিনিসপত্র'
        ],
        'restaurant_and_catering_business' => [
            'en' =>  'Restaurant and Catering Business',
            'bn' =>  'রেস্টুরেন্ট ও ক্যাটারিং ব্যবসায়'
        ],
        'motor_parts_business' => [
            'en' =>  'Motor Parts Business',
            'bn' =>  'মোটর যন্ত্রাংশের ব্যবসায়'
        ],
        'service' => [
            'en' =>  'Service & Repairing',
            'bn' =>  'সার্ভিস'
        ],
        'rent-a-car_or_car_rental_business' => [
            'en' =>  'Rent-a-car or Car Rental Business',
            'bn' =>  'রেন্ট-এ-কার বা গাড়ি ভাড়া ব্যবসায়্'
        ],
        'toy_store' => [
            'en' =>  'Toy Store',
            'bn' =>  'খেলনার দোকান'
        ],
        'trading' => [
            'en' => 'Trading',
            'bn' => 'ট্রেডিং'
        ],
        'manufacturing' => [
            'en' => 'Manufacturing',
            'bn' => 'ম্যানুফ্যাকচারিং'
        ],
        'beauty_and_salon' => [
            'en' =>  'Beauty & Salon',
            'bn' =>  'বিউটি ও সেলুন'
        ],
        'laundry' => [
            'en' =>  'Laundry',
            'bn' =>  'লন্ড্রি'
        ],
        'ticket_and_travel_business' => [
            'en' =>  'Ticket & Travel Business',
            'bn' =>  'টিকেট ও ট্রাভেলস ব্যবসায়'
        ],
        'house_shifting_business' => [
            'en' =>  'House Shifting Business',
            'bn' =>  'বাসা বদল ব্যবসা'
        ],
        'painting_and_renovation' => [
            'en' =>  'Painting and Renovation',
            'bn' =>  'পেইন্টিং ও রেনোভেশন'
        ],
        'eyewear_store' => [
            'en' =>  'Eyewear Store',
            'bn' =>  'চশমার দোকান'
        ],
        'handicraft_business' => [
            'en' =>  'Handicraft Business',
            'bn' =>  'হ্যান্ডি ক্রাফট ব্যবসায়'
        ],
        'others' => [
            'en' =>  'Others',
            'bn' =>  'অন্যান্য'
        ]
    ],
    "PARTNER_ORDER_TARGET_TYPE" => 'PARTNER_ORDER_DETAILS',
    'PARTNER_SUBSCRIPTION_SMS' => env('PARTNER_SUBSCRIPTION_SMS'),

    'MAX_CONCURRENT_MIDDLEWARE_TIME' => 180,
];
