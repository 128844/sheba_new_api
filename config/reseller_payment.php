<?php

use Sheba\Payment\Factory\PaymentStrategy;

return [
    'mor_access_token'               => env('MOR_ACCESS_TOKEN'),
    'mor'                            => [
        'api_url'       => env('MOR_SERVICE_API_URL'),
        'client_id'     => env('MOR_CLIENT_ID', 1234),
        'client_secret' => env('MOR_CLIENT_SECRET', 'abcd')
    ],
    "available_payment_gateway_keys" => [PaymentStrategy::SSL],
    'category_list'                  => [
        'ssl'   => [
            'nid_selfie'  => 'NIDSelfie',
            'institution' => 'Institution',
            'personal'    => 'Personal',
            'documents'   => 'Documents'
        ],
        'bkash' => [
            'nid_selfie'  => 'NIDSelfie',
            'institution' => 'Institution',
            'personal'    => 'Personal',
            'documents'   => 'Documents'
        ],
    ],
    'completion_message'             => [
        'mtb'      => [
            "incomplete_message" => "MTB QR সার্ভিস সচল করতে প্রয়োজনীয় তথ্য প্রদান করুন।",
            "completed_message"  => "প্রয়োজনীয় তথ্য দেয়া সম্পন্ন হয়েছ, MTB QR সার্ভিস সচল করতে আবেদন করুন।"
        ],
        'aamarpay' => [
            "incomplete_message" => "AAMARPAY সার্ভিস সচল করতে প্রয়োজনীয় তথ্য প্রদান করুন।",
            "completed_message"  => "প্রয়োজনীয় তথ্য দেয়া সম্পন্ন হয়েছ, AAMARPAY সার্ভিস সচল করতে আবেদন করুন।"
        ]
    ],
    'exclude_form_keys'              => [
        'ssl'   => [
            'nid_selfie'  => [],
            'institution' => [],
            'personal'    => [],
            'documents'   => [],
        ],
        'bkash' => [
            'institution' => [],
            'nid_selfie'  => [],
            'personal'    => [],
            'documents'   => [],
        ]
    ],
    'required_documents'             => [
        'ssl'      => [
            [
                'name'    => 'trade_licence',
                'name_bn' => 'ট্রেড লাইসেন্স',
                'icon'    => 'https://cdn-shebaxyz.s3.ap-south-1.amazonaws.com/partner/reseller_payment/trade_licence_icon.png'
            ],
            [
                'name'    => 'tin_certificate',
                'name_bn' => 'টিন সার্টিফিকেট',
                'icon'    => 'https://cdn-shebaxyz.s3.ap-south-1.amazonaws.com/partner/reseller_payment/tin_certificate_icon.png'
            ],
            [
                'name'    => 'vat_certificate',
                'name_bn' => 'ভ্যাট সার্টিফিকেট',
                'icon'    => 'https://cdn-shebaxyz.s3.ap-south-1.amazonaws.com/partner/reseller_payment/vat_certificate_icon.png'
            ]
        ],
        'bkash'    => [],
        'mtb'      => [
            [
                'name'    => 'trade_licence',
                'name_bn' => 'ট্রেড লাইসেন্স',
                'icon'    => 'https://cdn-shebaxyz.s3.ap-south-1.amazonaws.com/partner/reseller_payment/trade_licence_icon.png'
            ],
            [
                'name'    => 'tin_certificate',
                'name_bn' => 'টিন সার্টিফিকেট',
                'icon'    => 'https://cdn-shebaxyz.s3.ap-south-1.amazonaws.com/partner/reseller_payment/tin_certificate_icon.png'
            ],
            [
                'name'    => 'vat_certificate',
                'name_bn' => 'ভ্যাট সার্টিফিকেট',
                'icon'    => 'https://cdn-shebaxyz.s3.ap-south-1.amazonaws.com/partner/reseller_payment/vat_certificate_icon.png'
            ]
        ],
        'aamarpay' => [
            [
                'name'    => 'trade_licence',
                'name_bn' => 'ট্রেড লাইসেন্স',
                'icon'    => 'https://cdn-shebaxyz.s3.ap-south-1.amazonaws.com/partner/reseller_payment/trade_licence_icon.png'
            ],
            [
                'name'    => 'tin_certificate',
                'name_bn' => 'টিন সার্টিফিকেট',
                'icon'    => 'https://cdn-shebaxyz.s3.ap-south-1.amazonaws.com/partner/reseller_payment/tin_certificate_icon.png'
            ],
            [
                'name'    => 'photo_of_signatories',
                'name_bn' => 'অনুমোদিত স্বাক্ষরকারীদের ছবি',
                'icon'    => 'https://cdn-shebaxyz.s3.ap-south-1.amazonaws.com/partner/reseller_payment/vat_certificate_icon.png'
            ]
        ]
    ],
    'document_service_list'          => [
        'ssl'   => [
            'message'              => 'ডকুমেন্ট সার্ভিস গ্রহণ করতে নিচের নাম্বারে যোগাযোগ করুন :',
            'service_contact_list' => [
                [
                    'name'    => 'F M Associates',
                    'mobile'  => '+880179614836499',
                    'address' => '51, Green Corner',
                    'photo'   => 'https://cdn-shebaxyz.s3.ap-south-1.amazonaws.com/partner/reseller_payment/vat_certificate_icon.png'
                ],
                [
                    'name'    => 'Woliur Business',
                    'mobile'  => '+880179614836499',
                    'address' => '51, Green Corner',
                    'photo'   => 'https://cdn-shebaxyz.s3.ap-south-1.amazonaws.com/partner/reseller_payment/vat_certificate_icon.png'

                ],
                [
                    'name'    => 'Firoze Associates',
                    'mobile'  => '+880179614836499',
                    'address' => '51, Green Corner',
                    'photo'   => 'https://cdn-shebaxyz.s3.ap-south-1.amazonaws.com/partner/reseller_payment/vat_certificate_icon.png'

                ],
                [
                    'name'    => 'F M Associates',
                    'mobile'  => '+880179614836499',
                    'address' => '51, Green Corner',
                    'photo'   => 'https://cdn-shebaxyz.s3.ap-south-1.amazonaws.com/partner/reseller_payment/vat_certificate_icon.png'

                ]
            ]
        ],
        'bkash' => []
    ],
    'category_titles'                => [
        'nid_selfie'  => [
            'en' => 'NID and Selfie',
            'bn' => 'জাতীয় পরিচয়পত্র ও সেলফি'
        ],
        'institution' => [
            'en' => 'Institution',
            'bn' => 'প্রতিষ্ঠান সম্পর্কিত তথ্য'
        ],
        'personal'    => [
            'en' => 'Personal',
            'bn' => 'ব্যক্তিগত তথ্য '
        ],
        'documents'   => [
            'en' => 'Documents',
            'bn' => 'প্রয়ােজনীয় ডকুমেন্ট আপলোড'
        ]
    ],
    "category_form_items"            => [
        'institution' => [
            [
                'id'          => 'header',
                'input_type'  => 'header',
                "message"     => "কোম্পানি প্রোফাইল",
                "hint"        => "",
                'mandatory'   => false,
                'is_editable' => false,
            ],
            [
                'label'          => 'কোম্পানির নাম * ',
                'message'        => '',
                'hint'           => 'কোম্পানির নাম লিখুন',
                'id'             => 'company_name',
                'error'          => "কোম্পানির নাম পূরণ আবশ্যক",
                'input_type'     => 'text',
                'data'           => '',
                "min_length"     => "",
                "max_length"     => "",
                'is_editable'    => true,
                'mandatory'      => false,
                'data_source'    => 'partner',
                'data_source_id' => 'name'
            ],
            [
                'label'          => 'ট্রেড লাইসেন্স নং *',
                'message'        => '',
                'hint'           => 'ট্রেড লাইসেন্স নং লিখুন',
                'id'             => 'trade_license',
                'error'          => "ট্রেড লাইসেন্স নং পূরণ আবশ্যক",
                'input_type'     => 'text',
                'data'           => '',
                "min_length"     => "",
                "max_length"     => "",
                'is_editable'    => true,
                'mandatory'      => false,
                'data_source'    => 'partner_basic_information',
                'data_source_id' => 'trade_license'
            ],
            [
                'label'          => 'টিন নম্বর *',
                'message'        => '',
                'hint'           => 'টিন নম্বর লিখুন',
                'id'             => 'tin_no',
                'error'          => "টিন নম্বর পূরণ আবশ্যক",
                'input_type'     => 'text',
                'data'           => '',
                "min_length"     => "",
                "max_length"     => "",
                'is_editable'    => true,
                'mandatory'      => false,
                'data_source'    => 'first_admin_profile',
                'data_source_id' => 'tin_no'
            ],
            [
                'label'          => 'কোম্পানির টাইপ *',
                'message'        => '',
                'hint'           => '',
                'id'             => 'company_type',
                'error'          => "কোম্পানির টাইপ পূরণ আবশ্যক",
                'input_type'     => 'dropdown',
                'data'           => '',
                "min_length"     => "",
                "max_length"     => "",
                'is_editable'    => true,
                'mandatory'      => false,
                'data_source'    => 'partner',
                'data_source_id' => 'business_type'
            ],
            [
                'label'          => 'রেজিস্টার্ড ঠিকানা *',
                'message'        => "",
                'hint'           => 'কোম্পানির ঠিকানা লিখুন',
                'id'             => 'registered_address',
                'error'          => "রেজিস্টার্ড ঠিকানা পূরণ আবশ্যক",
                'input_type'     => 'text',
                'data'           => "",
                "min_length"     => "",
                "max_length"     => "",
                'is_editable'    => true,
                'mandatory'      => false,
                'data_source'    => 'partner',
                'data_source_id' => 'address'
            ],
            [
                'label'            => 'সাপোর্ট নাম্বার *',
                'message'          => '',
                'hint'             => '01762180533',
                'id'               => 'support_number',
                'error'            => "সাপোর্ট নাম্বার পূরণ আবশ্যক",
                'input_type'       => 'phone',
                'data'             => "",
                "min_length"       => "",
                "max_length"       => "",
                'is_editable'      => false,
                'mandatory'        => false,
                'data_source'      => 'partner',
                'data_source_id'   => 'getContactNumber',
                'data_source_type' => 'function'
            ],
            [
                'label'          => 'আপনি ব্যবসা শুরু করেছেন কবে *',
                'message'        => '',
                'hint'           => '05/11/2012',
                'id'             => 'registration_year',
                'error'          => "পূরণ আবশ্যক",
                'input_type'     => 'date_picker',
                'data'           => "",
                "min_length"     => "",
                "max_length"     => "",
                'is_editable'    => true,
                'mandatory'      => false,
                'data_source'    => 'partner_basic_information',
                'data_source_id' => 'registration_year'
            ],
            [
                'label'       => 'কোম্পানির মোট মূল্য (আনুমানিক) *',
                'message'     => "",
                'hint'        => 'কোম্পানির মোট মূল্য লিখুন',
                'id'          => 'company_total_value',
                'error'       => "পূরণ আবশ্যক",
                'input_type'  => 'number',
                'data'        => "",
                "min_length"  => "",
                "max_length"  => "",
                'is_editable' => true,
                'mandatory'   => false,
                'data_source' => 'json'
            ],
            [
                'label'       => "মার্চেন্টের ধরণ",
                'message'     => '',
                'hint'        => '',
                'id'          => 'type_of_merchant',
                'error'       => "পূরণ আবশ্যক",
                'input_type'  => 'text',
                'data'        => "MSME",
                "min_length"  => "",
                "max_length"  => "",
                'is_editable' => false,
                'mandatory'   => false,
                'data_source' => 'json'
            ],
            [
                'id'          => 'header',
                'input_type'  => 'header',
                "message"     => "কোম্পানির ব্যাংক তথ্য",
                "hint"        => "",
                'mandatory'   => false,
                'is_editable' => false,
            ],
            [
                'label'          => "ব্যাংক অ্যাকাউন্টের নাম *  ",
                'message'        => "",
                'hint'           => 'অ্যাকাউন্টের নাম লিখুন',
                'id'             => 'acc_name',
                'error'          => "অ্যাকাউন্টের নাম পূরণ আবশ্যক",
                'input_type'     => 'text',
                'data'           => "",
                "min_length"     => "",
                "max_length"     => "",
                'is_editable'    => true,
                'mandatory'      => false,
                'data_source'    => 'partner_bank_information',
                'data_source_id' => 'acc_name'
            ],
            [
                'label'          => "ব্যাংকের নাম",
                'message'        => "",
                'hint'           => 'ব্যাংকের নাম লিখুন',
                'id'             => 'bank_name',
                'error'          => "ব্যাংকের নাম পূরণ আবশ্যক",
                'input_type'     => 'text',
                'data'           => "",
                "min_length"     => "",
                "max_length"     => "",
                'is_editable'    => true,
                'mandatory'      => false,
                'data_source'    => 'partner_bank_information',
                'data_source_id' => 'bank_name'
            ],
            [
                'label'          => "ব্যাংকের ঠিকানা ",
                'message'        => "",
                'hint'           => "ব্যাংকের ঠিকানা লিখুন",
                'id'             => 'bank_address',
                'error'          => "ব্যাংকের ঠিকানা পূরণ আবশ্যক",
                'input_type'     => 'text',
                'data'           => "",
                "min_length"     => "",
                "max_length"     => "",
                'is_editable'    => true,
                'mandatory'      => false,
                'data_source'    => 'partner_bank_information',
                'data_source_id' => 'bank_address'
            ],
            [
                'label'          => "ব্যাংক অ্যাকাউন্ট নং *",
                'message'        => "",
                'hint'           => "অ্যাকাউন্ট নং লিখুন",
                'id'             => 'acc_no',
                'error'          => "ব্যাংক অ্যাকাউন্ট নং পূরণ আবশ্যক",
                'input_type'     => 'text',
                'data'           => "",
                "min_length"     => "",
                "max_length"     => "",
                'is_editable'    => true,
                'mandatory'      => false,
                'data_source'    => 'partner_bank_information',
                'data_source_id' => 'acc_no'
            ],
            [
                'label'          => "ব্যাংক রাউটিং নাম্বার *",
                'message'        => "",
                'hint'           => "রাউটিং নাম্বার লিখুন",
                'id'             => 'routing_no',
                'error'          => "ব্যাংক রাউটিং নাম্বার পূরণ আবশ্যক",
                'input_type'     => 'text',
                'data'           => "",
                "min_length"     => "",
                "max_length"     => "",
                'is_editable'    => true,
                'mandatory'      => false,
                'data_source'    => 'partner_bank_information',
                'data_source_id' => 'routing_no'
            ],
            [
                'id'          => 'header',
                'input_type'  => 'header',
                "message"     => "লেনদেন সম্পর্কিত তথ্য",
                "hint"        => "",
                'mandatory'   => false,
                'is_editable' => false,
            ],
            [
                'label'       => "আপনার ব্যবসাতে প্রতি মাসে কয়টি লেনদেন হয় * ",
                'message'     => "",
                'hint'        => "মাসিক লেনদেনের পরিমাণ লিখুন",
                'id'          => 'monthly_transaction_numbers',
                'error'       => "পূরণ আবশ্যক",
                'input_type'  => 'number',
                'data'        => "",
                "min_length"  => "",
                "max_length"  => "",
                'is_editable' => true,
                'mandatory'   => false,
                'data_source' => 'json',
            ],
            [
                'label'       => "আপনার ব্যবসাতে প্রতি মাসে কত টাকার লেনদেন হয় * ",
                'message'     => "",
                'hint'        => "মাসিক লেনদেনের পরিমাণ লিখুন",
                'id'          => 'monthly_transaction_amount',
                'error'       => "পূরণ আবশ্যক",
                'input_type'  => 'decimal_number',
                'data'        => "",
                "min_length"  => "",
                "max_length"  => "",
                'is_editable' => true,
                'mandatory'   => false,
                'data_source' => 'json',
            ],
            [
                'label'       => "আপনার ব্যবসাতে ১ টি লেনদেন এ সর্বোচ্চ লেনদেন হয় *",
                'message'     => "",
                'hint'        => "সর্বোচ্চ ১তি লেনদেনের পরিমাণ লেখুন",
                'id'          => 'highest_transaction_amount',
                'error'       => "পূরণ আবশ্যক",
                'input_type'  => 'decimal_number',
                'data'        => "",
                "min_length"  => "",
                "max_length"  => "",
                'is_editable' => true,
                'mandatory'   => false,
                'data_source' => 'json',
            ],
            [
                'label'       => "আপনার ব্যবসাতে প্রতি দিনে কয়টি লেনদেন হয় *",
                'message'     => "",
                'hint'        => "প্রতিদিনের লেনদেনের পরিমাণ লিখুন",
                'id'          => 'daily_transaction_number',
                'error'       => "পূরণ আবশ্যক",
                'input_type'  => 'number',
                'data'        => "",
                "min_length"  => "",
                "max_length"  => "",
                'is_editable' => true,
                'mandatory'   => false,
                'data_source' => 'json',
            ],
            [
                'label'       => "আপনার ব্যবসাতে প্রতি দিনে কত টাকার লেনদেন হয় *",
                'message'     => "",
                'hint'        => "দৈনিক লেনদেনের পরিমাণ লিখুন",
                'id'          => 'daily_transaction_amount',
                'error'       => "পূরণ আবশ্যক",
                'input_type'  => 'decimal_number',
                'data'        => "",
                "min_length"  => "",
                "max_length"  => "",
                'is_editable' => true,
                'mandatory'   => false,
                'data_source' => 'json',
            ],
        ],
        'documents'   => [
            [
                'label'          => 'ট্রেড লাইসেন্সের ছবি',
                'message'        => '',
                'hint'           => '',
                'id'             => 'trade_license_attachment',
                'error'          => "Trade licence photo is required",
                'input_type'     => 'document',
                'data'           => '',
                "min_length"     => "",
                "max_length"     => "",
                'is_editable'    => true,
                'mandatory'      => false,
                'data_source'    => 'partner_basic_information',
                'data_source_id' => 'trade_license_attachment',
                'upload_folder'  => 'getTradeLicenceDocumentsFolder'
            ],
            [
                'label'          => 'টিন সার্টিফিকেটের ছবি',
                'message'        => '',
                'hint'           => '',
                'id'             => 'tin_certificate',
                'error'          => "Tin photo is required",
                'input_type'     => 'document',
                'data'           => '',
                "min_length"     => "",
                "max_length"     => "",
                'is_editable'    => true,
                'mandatory'      => false,
                'data_source'    => 'first_admin_profile',
                'data_source_id' => 'tin_certificate',
                'upload_folder'  => 'getVatRegistrationImagesFolder'
            ],
            [
                'label'          => 'ভ্যাট সার্টিফিকেটের ছবি',
                'message'        => '',
                'hint'           => '',
                'id'             => 'vat_registration_attachment',
                'error'          => "Vat registration is required",
                'input_type'     => 'document',
                'data'           => '',
                "min_length"     => "",
                "max_length"     => "",
                'is_editable'    => true,
                'mandatory'      => false,
                'data_source'    => 'partner_basic_information',
                'data_source_id' => 'vat_registration_attachment',
                'upload_folder'  => 'getVatRegistrationImagesFolder'
            ],
        ],
        'personal'    => [
            [
                'label'          => 'নাম *',
                'message'        => '',
                'hint'           => 'Suniv Ashraf',
                'id'             => 'user_name',
                'error'          => "আপনার  নাম পূরণ আবশ্যক",
                'input_type'     => 'text',
                'data'           => '',
                "min_length"     => "",
                "max_length"     => "",
                'is_editable'    => false,
                'mandatory'      => false,
                'data_source'    => 'partner_resource_profile',
                'data_source_id' => 'name'
            ],
            [
                'label'          => 'ই-মেইল *',
                'message'        => '',
                'hint'           => 'ইমেইল এড্রেস লিখুন',
                'id'             => 'email',
                'error'          => "ইমেইল পূরণ আবশ্যক",
                'input_type'     => 'text',
                'data'           => '',
                "min_length"     => "",
                "max_length"     => "",
                'is_editable'    => true,
                'mandatory'      => false,
                'data_source'    => 'partner_resource_profile',
                'data_source_id' => 'email'
            ],
            [
                'label'          => 'ফোন নাম্বার *',
                'message'        => '',
                'hint'           => '01762180533',
                'id'             => 'mobile',
                'error'          => "ফোন নাম্বার পূরণ আবশ্যক",
                'input_type'     => 'text',
                'data'           => '',
                "min_length"     => "",
                "max_length"     => "",
                'is_editable'    => false,
                'mandatory'      => false,
                'data_source'    => 'partner_resource_profile',
                'data_source_id' => 'mobile'
            ],
            [
                'label'          => 'এনআইডি নং *',
                'message'        => "",
                'hint'           => '68464684936',
                'id'             => 'nid_no',
                'error'          => "এনআইডি নং পূরণ আবশ্যক",
                'input_type'     => 'text',
                'data'           => "",
                "min_length"     => "",
                "max_length"     => "",
                'is_editable'    => false,
                'mandatory'      => false,
                'data_source'    => 'partner_resource_profile',
                'data_source_id' => 'nid_no'
            ],
            [
                'label'          => 'জন্ম তারিখ *',
                'message'        => '',
                'hint'           => '1989-03-16',
                'id'             => 'dob',
                'error'          => "পজন্ম তারিখ ূরণ আবশ্যক",
                'input_type'     => 'date_picker',
                'data'           => "",
                "min_length"     => "",
                "max_length"     => "",
                'is_editable'    => false,
                'mandatory'      => false,
                'data_source'    => 'partner_resource_profile',
                'data_source_id' => 'dob'
            ]

        ],
    ],
    "status_wise_home_banner"        => [
        'pgw_inactive'                => 'https://cdn-shebaxyz.s3.ap-south-1.amazonaws.com/partner/reseller_payment/homepage_banner/pgw_inactive.png',
        'verified'                    => 'https://cdn-shebaxyz.s3.ap-south-1.amazonaws.com/partner/reseller_payment/homepage_banner/verified.png',
        'rejected'                    => 'https://cdn-shebaxyz.s3.ap-south-1.amazonaws.com/partner/reseller_payment/homepage_banner/rejected.png',
        'completed_but_did_not_apply' => 'https://cdn-shebaxyz.s3.ap-south-1.amazonaws.com/partner/reseller_payment/homepage_banner/pending.png',
        "ekyc_completed"              => 'https://cdn-shebaxyz.s3.ap-south-1.amazonaws.com/partner/reseller_payment/homepage_banner/ekyc_completed.png',
        'did_not_started_journey'     => 'https://cdn-shebaxyz.s3.ap-south-1.amazonaws.com/partner/reseller_payment/homepage_banner/not_started_journey.png'
    ],
    'mor_status_wise_text'           => [
        'ssl'      => [
            'pending'        => 'আপনার আবেদনটি সফলভাবে সম্পন্ন হয়েছে অনুমোদনের জন্য অনুগ্রহ করে ১০ কার্যদিবস অপেক্ষা করুন।',
            'processing'     => 'আপনার আবেদনটি সফলভাবে সম্পন্ন হয়েছে অনুমোদনের জন্য অনুগ্রহ করে ১০ কার্যদিবস অপেক্ষা করুন।',
            'verified'       => 'আপনার আবেদনটি সফলভাবে অনুমোদন হয়েছে অনুগ্রহ করে আপনার ইমেইল চেক করুন এবং প্রয়োজনীয় তথ্য দিয়ে পেমেন্ট সার্ভিস সেট-আপ করুন।',
            'rejected_start' => 'আপনার আবেদনটি অনুমোদন করা সম্ভব হয়নি কারণ - ',
            'rejected_end'   => ' সঠিক তথ্য দিয়ে পুনরায় আবেদন করুন।'
        ],
        'aamarpay' => [
            'pending'        => 'আপনার আবেদনটি সফলভাবে সম্পন্ন হয়েছে অনুমোদনের জন্য অনুগ্রহ করে ১০ কার্যদিবস অপেক্ষা করুন।',
            'processing'     => 'আপনার আবেদনটি সফলভাবে সম্পন্ন হয়েছে অনুমোদনের জন্য অনুগ্রহ করে ১০ কার্যদিবস অপেক্ষা করুন।',
            'verified'       => 'আপনার আবেদনটি সফলভাবে অনুমোদন হয়েছে অনুগ্রহ করে আপনার ইমেইল চেক করুন এবং প্রয়োজনীয় তথ্য দিয়ে পেমেন্ট সার্ভিস সেট-আপ করুন।',
            'rejected_start' => 'আপনার আবেদনটি অনুমোদন করা সম্ভব হয়নি কারণ - ',
            'rejected_end'   => ' সঠিক তথ্য দিয়ে পুনরায় আবেদন করুন।'
        ]
    ],
    "payment_gateway_status_message" => [
        "ssl" => [
            'processing' => 'আপনার আবেদনটি সফলভাবে সম্পন্ন হয়েছে অনুমোদনের জন্য অনুগ্রহ করে ১০ কার্যদিবস অপেক্ষা করুন।',
            'verified'   => 'আপনার আবেদনটি সফলভাবে অনুমোদন হয়েছে অনুগ্রহ করে আপনার ইমেইল চেক করুন এবং প্রয়োজনীয় তথ্য দিয়ে পেমেন্ট সার্ভিস সেট-আপ করুন।',
            'rejected'   => 'আপনার আবেদনটি অনুমোদন করা সম্ভব হয়নি কারণ - Rejection Note সঠিক তথ্য দিয়ে পুনরায় আবেদন করুন।'
        ]
    ],
    'mor_status_change_message'      => [
        "ssl" => [
            "processing" => 'SSL এ আপনার মার্চেন্ট অনবোর্ড রেকুয়েস্ট নিয়ে কাজ শুরু হয়েছে, আগামী ১০ কার্যদিবস এর মধ্যে ফলাফল জানানো হবে।',
            "verified"   => 'SSL এ আপনার মার্চেন্ট অনবোর্ড রেকুয়েস্ট সফলভাবে সম্পন্ন হয়েছে, বিস্তারিত জানতে মেইল চেক করুন।',
            "rejected"   => 'SSL এ আপনার মার্চেন্ট অনবোর্ড রেকুয়েস্টটি সফল হয়নি, পুনরায় চেষ্টা করুন।'
        ]
    ]
];
