<?php

namespace App\Sheba\MTB;

use Sheba\Helpers\ConstGetter;

class MtbAccountStatusConstants
{
    use ConstGetter;

    const REQUEST_PLACED = "আপনার আবেদনটি সফলভাবে সম্পন্ন হয়েছে ";
    const CIF_CREATED = "আপনার আবেদনটি একাউন্ট খোলার জন্য প্রসেসিং এ আছে ";
    const ACCOUNT_OPENED_CBS = "সফলভাবে আপনার ব্যাংক একাউন্ট খোলা হয়েছে ";
    const MERCHANT_CREATED = "সফলভাবে আপনার মার্চেন্ট একাউন্ট টি খোলা হয়েছে ";
    const REQUEST_CANCELLED = "আপনার আবেদনটি ব্যাংক কর্তৃক বাতিল করা হয়েছে,বিস্তারিত জানতে 16516 এ কল করুন ";
}
