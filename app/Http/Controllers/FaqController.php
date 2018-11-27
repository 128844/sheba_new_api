<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function getAffiliateFaqs(Request $request)
    {
        try {
            $faqs = array(
                array(
                    'group_label_bn' => 'সেবা বন্ধু',
                    'group_label_en' => 'Sheba Bondhu',
                    'group_name' => 'sheba_bondhu',
                    'questions' => array(
                        array(
                            'question_bn' => 'সেবা বন্ধু কী?',
                            'answer_bn' => 'সেবা বন্ধু হচ্ছে এমন একটি অ্যাপ যার মাধ্যমে আপনি আপনার বন্ধুকে হেল্প করার পাশাপাশি সহজে টাকা আয় করতে পারবেন। সেবা বন্ধুতে আপনি সার্ভিস, আপনার ব্যবসায়ী বন্ধুকে রেফার অথবা টপ আপ এর মাধ্যমে টাকা আয় করতে পারবেন।',
                            'question_en' => 'What is SHEBA Bondhu APP?',
                            'answer_en'=> 'Sheba Bondhu is an APP which enable you to earn money by helping your friend. In Sheba Bondhu APP you can earn money by referring Service, Businessman Friend or top-up to your friend.'
                        ),
                        array(
                            'question_bn' => 'আমার একাউন্ট ভেরিফাইড হয়নি কেন?',
                            'answer_bn' => 'আপনার একাউন্টটি ভেরিফাইড করতে আপনাকে অবশই আপনার বিকাশ নাম্বারটি প্রদান করতে হবে। তার পরও যদি ভেরিফাইড না হয় তবে ১৬৫১৬ এ যোগাযোগ করুন।',
                            'question_en' => 'Why my account is not verified yet?',
                            'answer_en'=> 'You need to input your BKash Number to verify your account. If it not verified after that then you need to 1516 for help.'
                        ),
                    )
                ),
                array(
                    'group_label_bn' => 'সার্ভিস রেফার কিভাবে কাজ করে?',
                    'group_label_en' => 'How does Service Refer work',
                    'group_name' => 'service_refer',
                    'questions' => array(
                        array(
                            'question_bn' => 'সার্ভিস রেফার কিভাবে কাজ করে?',
                            'answer_bn' => 'আপনার বন্ধুর যদি কোন সার্ভিস প্রয়োজন হয় আপনি তার জন্য সেবাতে বন্ধু অ্যাপ এর মাধ্যমে রেফের করতে পারবেন। আপনার রেফারকৃত বন্ধুকে সেবা হেল্প ডেস্ক হতে দ্রুত কল করা হবে এবং তার সার্ভিসটি প্রদান এর জন্য সেবাতে একটি অর্ডার প্লেস করা হবে। একজন সেবা রেজিস্টার্ড সার্ভিস প্রোভাইডার আপনার বন্ধুর নিকট পৌঁছে যাবে।',
                            'question_en' => 'How does service referral work?  ',
                            'answer_en'=> 'You can refer service for your friend in sheba by this APP. Within a short time, your referred friend will receive a call from Sheba Help Desk and an order will placed. Sheba registered service provider will go to your friend to give his service. '
                        ),
                        array(
                            'question_bn' => 'সার্ভিস রেফার এর মাধ্যমে কিভাবে টাকা আয় করবেন?',
                            'answer_bn' => 'বন্ধু অ্যাপ এর মাধ্যমে সেবা তে আপনি আপনার পরিচিত জনের জন্য সার্ভিস রেফার করতে পারবেন। আগ্রহী গ্রাহকের নাম, ফোন নাম্বার ও কোন সার্ভিসটি লাগবে এই তথ্য গুল দিয়ে রেফার করুন। প্রতিটি রেফার সফল ভাবে সম্পন্ন হতেই আপনার বন্ধু মানিব্যাগ এ সার্ভিস মূল্যের ৫% বোনাস পেয়ে যাবেন।',
                            'question_en' => 'How to earn money by referring service for your friend? ',
                            'answer_en' => 'You can refer a service to your friend via Sheba Bondhu APP. By giving information about Customer Name, Phone and Needed Service name you can refer your friend. On every successful refer you will earn 5% of order amount as bonus in your Bondhu moneybag'
                        ),
                    )
                ),
                array(
                    'group_label_bn' => 'কিভাবে সার্ভিস প্রোভাইডার রেফার কাজ করে?',
                    'group_label_en' => 'How Service provider referral work?',
                    'group_name' => 'service_provider',
                    'questions' => array(
                        array(
                            'question_bn' => 'কিভাবে সার্ভিস প্রোভাইডার রেফার কাজ করে?',
                            'answer_bn' => 'আপনি কোন ব্যাবসায়ী বন্ধুকে রেফার করলে সেই রেফার ক্রিত নম্বার এ একটি মাসেজ এর মাধ্যমে সেবা মানেজার অ্যাপ এর লিঙ্ক পাঠানো হবে। আপনার বন্ধুকে সেই অ্যাপটি ডাউনলোড করে অ্যাপটি ইন্সটল করতে হবে। ডাউনলোড সম্পন্ন হলে অ্যাপ এর মাধ্যমে সার্ভিস প্রোভাইডার হিসাবে রেজিস্টার করতে হবে। সকল আবশ্যক তথ্য প্রদানের পর আপনার রেফের ক্রিত বন্ধুটি Ready to Verified হবে। সে সেবা দ্বারা ভেরিফাইড হওয়ার পর আপনি প্রথম কিস্তি বোনাস পাবেন। আপনার বন্ধুটি প্রথম দুটি কাজ সফল ভাবে সম্পন করলে আপনি বাকি বোনাস পায়ে যাবেন।',
                            'question_en' => 'How does service provider referral work?  ',
                            'answer_en'=> 'When you refer your business man friend, it will develop and send a massage to your friend phone with a link of Sheba Manager APP. Manger App need to download and install to his phone. He needs to get registered by giving all the valid necessary information. Sheba team will verify the service provider and he will able to serve. After completing verification, you will get first step of bonus. After completing two successful job Bondhu will get the bonus.'
                        ),
                        array(
                            'question_bn' => 'কিভাবে আপনার ব্যাবসায়ী বন্ধুকে রেফার করে টাকা আয় করবেন?',
                            'answer_bn' => 'আপনি আপনার বন্ধু অ্যাপ এর মাধ্যমে আপনার ব্যাবসায়ী বন্ধুকে রেফার করতে পারবেন। আপনার বন্ধুর নাম, ফোন নং এবং সার্ভিস নাম প্রদান করে আপনি তাকে সেবাতে রেফার করতে পারবেন। আর প্রতিটি সফল রেফের এ আপনি পাবেন ১৪০ টাকা বোনাস। এই বোনাস আপনি পাবেন দুটি কিস্তিতে- (১) রেজিস্টারড বন্ধুটি যখন সেবা দ্বারা ভেরিফাইড হবে তখন বোনাস পাবেন ৪২টাকা। (২) দুটি সফল সার্ভিস প্রদান করলে বন্ধু পাবে বাকি ৯৮ টাকা।',
                            'question_en' => 'How to refer your businessman friend? ',
                            'answer_en' => 'You can refer your business man friend in sheba.xyz via Bondhu App. By providing your friend name, phone no and company name him in sheba as Service Provider. You will get TK140 in every successful refer. You will get the bonus in two steps –
- After complete his verification you will get Tk 42 as bonus.
- After completing two successful work Bondhu will get TK 98 as final bonus.',
                        ),
                    )
                ),
                array(
                    'group_label_bn' => 'মানিব্যাগ কি ?',
                    'group_label_en' => 'What is a Money bag?',
                    'group_name' => 'money_bag',
                    'questions' => array(
                        array(
                            'question_bn' => 'মানিব্যাগ কি ?',
                            'answer_bn' => 'আপনি বন্ধু অ্যাপ এর মাধ্যমে যে সকল লেনদেন করবেন তার বিবরণ পাবেন মানিব্যাগ এ। এছাড়াও আপনি আপনার মানিব্যাগ এ জমা অর্থ দিয়ে টপ আপ করতে পারবেন আপনার ফোনে।',
                            'question_en' => 'What is a Money bag?  ',
                            'answer_en'=> 'The amount in moneybag represent how much you have earned, deposit or spend through this app.You can use this money bag amount for TOP-UP.'
                        ),
                        /** !!----- DO NOT REMOVE THIS BLOCK OF CODE. -------!! Currently not used, but may be required for later. */
//                        array(
//                            'question_bn' => 'মানিব্যাগ হতে কিভাবে নগদ টাকা উত্তোলন করব?',
//                            'answer_bn' => 'এই অ্যাপ থেকে আয়কৃত টাকা প্রতি সপ্তাহে একবার আপনার দেয়া বিকাশ অ্যাকাউন্ট-এ ট্রান্সফার হয়ে যাবে। নিকটস্থ বিকাশ পয়েন্ট থেকে বিকাশ অ্যাকাউন্টের টাকা নগদ উত্তোলন করতে পারবেন। তবে টাকা উত্তোলন করতে হলে আপনার মানিব্যাগে সর্বনিম্ন ১০০ টাকা থাকতে হবে।।',
//                            'question_en' => 'How can I withdraw from my Money bag? ',
//                            'answer_en' => 'You will get the payment in your verified bKash no. that you provided in this app. You can cash out the amount from the nearest bKash point.'
//                        ),
                    )
                ),
                array(
                    'group_label_bn' => 'একাউন্ট স্থগিত হবার কারণ কী?',
                    'group_label_en' => 'THE REASON FOR SUSPENSION OF ACCOUNTS',
                    'group_name' => 'account_suspension',
                    'questions' => array(
                        array(
                            'question_bn' => 'আমার একাউন্ট স্থগিত হবার কারন কি কি?',
                            'answer_bn' => 'সার্ভিস রেফার এর ক্ষেত্রে আপনি যদি ১০ বার ভুল অথবা মিথ্যা রেফার করেন তবে আপনার একাউন্টটি স্থগিত করা হবে। আপনি দিনে ২০টির বেশি সার্ভিস রেফার করতে পারবেন না।',
                            'question_en' => 'What are the reasons behind my suspension ?  ',
                            'answer_en'=> 'Your account will be suspended by giving 10 fake or invalid reference. Your referral limit is 20 service(s) per day.'
                        ),
                        array(
                            'question_bn' => 'আমার একাউন্টটি কতদিন স্থগিত থাকবে?',
                            'answer_bn' => 'আপনার একাউন্টটি সর্বোচ্চ ৩ দিন স্থগিত থাকবে।',
                            'question_en' => 'How long my account will be suspended ? ',
                            'answer_en' => 'Suspension will go away after 3 days automatically.'
                        ),
                    )
                ),
                array(
                    'group_label_bn' => 'ওয়ালেট রিচার্জ',
                    'group_label_en' => 'Wallet Recharge',
                    'group_name' => 'reference_rejection',
                    'questions' => array(
                        array(
                            'question_bn' => 'ওয়ালেট রিচার্জঃ',
                            'answer_bn' => 'আপনি আপনার ওয়ালেটটি রিচার্জ করতে পারবেন। ওয়ালেট রিচার্জ করতে প্রথমে আপনার বন্ধু অ্যাপ এর মেনু অপশনে যান। মেনুতে রিচার্জ এ প্রবেশ করুন। এখানে আপনি বিকাশ নাম্বার এবং আপনার বন্ধু আইডি দেখতে পাবেন। এই নাম্বার এ আপনার বিকাশ এর পেমেন্ট আপশন থেকে প্রয়জনীয় টাকার আংক বিকাশ করুন। আপনার বিকাশের ট্রান্সেকশন আইডিটি রিচার্জ বক্স এ লিখুন এবং রিচার্জ এ ক্লিক করুন।',
                            'question_en' => 'Wallet Recharge:',
                            'answer_en'=> 'You can recharge your Bondhu wallet from sheba Bondhu app. To recharge your wallet you have to go to recharge menu of Bondhu wallet in menu bar. You will find a Bkash payment number & Bondhu ID. bkash your preferred amount to sheba on that number & use the Bondhu id in reference. The transection id that you get from massage please type that id on Transection filed & press recharge. Your wallet will be automatically updated in system.'
                        ),
                    )
                ),
                array(
                    'group_label_bn' => 'এজেন্ট ও এম্বাসেডর',
                    'group_label_en' => 'AGENT &amp; AMBASSADOR',
                    'group_name' => 'agent_ambassador',
                    'questions' => array(
                        array(
                            'question_bn' => 'এজেন্ট কি?',
                            'answer_bn' => 'সেবাতে রেজিস্টার্ড সকল বন্ধুকে সেবা বন্ধু এজেন্ট বলা হয়। সেবা বন্ধু এজেন্টগণ নিজে স্বাধীনভাবে বা একজন এম্বাসেডর এর সাথে থেকে রেফার করতে পারবেন।',
                            'question_en' => 'What is Agent?  ',
                            'answer_en'=> 'All users registered in sheba Bondhu is known as Agent. An Sheba Bondhu agent can able to refer independently or he/she can join under any ambassador.'
                        ),
                        array(
                            'question_bn' => 'এম্বাসেডর কি?',
                            'answer_bn' => 'সেবা বন্ধু অ্যাপ এ এম্বাসেডর হচ্ছে এজেন্ট নিয়োগকারি। একজন এম্বাসেডর একজন এজেন্ট ও। তবে এম্বাসেডর তার অধীনে এজেন্ট নিয়োগ করতে পারেন। এজেন্ট তার সেটিংস্‌ এর মাই অ্যাকাউন্ট অপশন থেকে যে কোন আম্বাসেডর এর কোড প্রবেশ এর মাধ্যমে তার এজেন্ট হতে পারবেন। এজেন্টদের প্রতিটি সফল সার্ভিস রেফারে মুনাফার ২০%  বোনাস এবং সার্ভিস প্রোভাইডার রেফার এ ৬০ টাকা বোনাস পাবেন।',
                            'question_en' => 'What is Ambassador?  ',
                            'answer_en'=> 'In Sheba, Bondhu app ambassador is agent recruiter. On the other hand, he is also an agent. An agent can add his ambassador from my accounts option. From every successful service refer ambassador will receive 20% of profit as bonus and every successful service provider refer he will get 60tk as a bonus.'
                        ),
                    )
                ),
                array(
                    'group_label_bn' => 'যেকোন ধরণের জিজ্ঞাসা আমি কোথায় করব?',
                    'group_label_en' => 'General Question',
                    'group_name' => 'helpline',
                    'questions' => array(
                        array(
                            'question_bn' => 'যেকোনো ধরণের জিজ্ঞাসা আমি কোথায় করব?',
                            'answer_bn' => 'যেকোনো জিজ্ঞাসার জন্য ১৬৫১৬ এ কল করুন।',
                            'question_en' => 'Whom to contact if I face any issue with this app?',
                            'answer_en'=> 'Call 16516 if you face any difficulties or need any kind of support.'
                        ),
                    )
                ),
                array(
                    'group_label_bn' => 'টপ-আপ কিভাবে কাজ করে?',
                    'group_label_en' => 'How TOP-UP works?',
                    'group_name' => 'TOP-UP',
                    'questions' => array(
                        array(
                            'question_bn' => 'টপ- আপ কিভাবে কাজ করে?',
                            'answer_bn' => 'বন্ধুতে আপনি টপ-আপ করার সুযোগ পাচ্ছেন সহজেই। আপনি আপনার বন্ধুর মোবাইলে টপ-আপ করার পাশাপাশি পাচ্ছেন ৩% ক্যাশ ব্যাক।',
                            'question_en' => 'How TOP-UP works?',
                            'answer_en'=> ' In Bondhu App you can also top-up facility. You can top-up for anyone mobile and get 3% cashback.'
                        ),
//            array(
//                'question_bn' => 'প্রোমোকোড ব্রডকাস্টিং',
//                'answer_bn' => 'প্রোমোকোড ব্রডকাস্টিং- আপনি আপনার প্রোমোকোডটি নতুন কাস্টমারদের বিতরণ করতে পারবেন। নতুন কাস্টমার, তার কাস্টমার অ্যাপদ্বারা অর্ডার প্লেস করার সময় আপনার প্রদত্ত কোডটি প্রোমোকোড হিসেবে ব্যাবহার করে সর্বোচ্চ ১০০ টাকা পর্যন্ত ডিস্কাউন্ট পাবে। এক্ষেত্রে প্রত্যেকটি সফল অর্ডার এর জন্য আপনি সেবার সার্ভিস চার্জ থেকে ৫০% (২০ টাকা থেকে সর্বোচ্চ ২০০ টাকা) পাবেন।',
//                'question_en' => 'Promo code broadcasting',
//                'answer_en'=> 'Promo code broadcasting- You can also distribute your promo code to the new users who are willing to place order via customer app. If new users use your ambassador code as a promo code while placing order. Customer will get maximum 100 BDT discount for his order. For the  each successful order you will get 50% of benefits from Sheba service charge (at least 20 BDT up-to 200 BDT) from each orders by new users.'
//            ),
                    ),
                ),
                array(
                    'group_label_bn' => 'এম্বাসেডর কোড',
                    'group_label_en' => 'Ambassador Code',
                    'group_name' => 'Ambassador_Code',
                    'questions' => array(
                        array(
                            'question_bn' => 'আমার কোডটি কিভাবে কাজ করবে? ',
                            'answer_bn' => ' এজেন্ট সংগ্রহ- এজেন্ট সংগ্রহ করার জন্য আপনার কোডটি তাদের সাথে শেয়ার করুন। আপনার এজেন্ট বন্ধু অ্যাপ দ্বারা রেফার বা টপ- আপ করলে, এজেন্ট দের প্রতিটি সফল সার্ভিস রেফারে সার্ভিস মূল্যের  এর ২% বোনাস এবং সার্ভিস প্রভাইডার রেফার এ ৬০ টাকা বোনাস পাবেন।  সার্ভিস প্রভাইডার রেফার বোনাস আপনি পাবেন দুটি কিস্তিতে- (১) রেজিস্টারড বন্ধুটি যখন সেবা দ্বারা ভেরিফাইড হবে তখন বোনাস পাবেন ১৮ টাকা। (২) একটি সফল সার্ভিস প্রদান করলে বন্ধু পাবে বাকি ৪২ টাকা। আপনি যতবেশি আপনার এজেন্ট সংগ্রহ করবেন, আপনার লাভবান হবার সম্ভাবনা তত বেড়ে যাবে। বেশি বেশি এজেন্ট সংগ্রহ করুন, বেশি বেশি উপার্জন করুন।',
                            'question_en' => 'How my code will work?',
                            'answer_en'=> ' Connect Agents- Share your code with your friends and connect them with your account.When your agents start referring to Sheba through Bondhu app, From every successful service an refer ambassador will receive 2% of Service Price and every successful service provider refer ambassador will get TK 60 as bonus. Service provider bonus will provide in two steps- (1) After verification of referred service provider 18tk, (2) After completing to successful job by the referred service provider, ambassador will get 42tk.'
                        ),

                    )
                ),
            );

            return api_response($request, $faqs, 200, ['faqs' => $faqs]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }
    public function getPartnerPerformanceFaqs(Request $request){
        try {
            $faqs = array(
                array(
                    'question_en' => null,
                    'question_bn' => 'পারফর্মেন্স বলতে কি বুঝায়?',
                    'list' => array(
                        array(
                            'title_bn' => null,
                            'answer_bn' => 'আপনি কাস্টমার এর কাছ থেকে যতগুলো অর্ডার গ্রহণ করেছেন তার মধ্যে কতটা সফল ভাবে সম্পন্ন করতে পেরেছেন তাই হচ্ছে পারফর্মেন্স।',
                            'title_en' => null,
                            'answer_en'=> null,
                            'asset_type' => 'none',
                            'emoji' => null,
                            'range_en' => null,
                            'range_bn' => null,
                        ),
                    )
                ),
                array(
                    'question_en' => null,
                    'question_bn' => 'কি কি বিষয়ের উপর পারফর্মেন্স নির্ভর করে?',
                    'list' => array(
                        array(
                            'title_bn' => 'সফল ভাবে সম্পন্ন',
                            'answer_bn' => 'আপনার প্রাপ্ত অর্ডার গুলোর মধ্যে কতগুলো অর্ডার সফলভাবে সম্পন্ন হয়েছে?',
                            'title_en' => null,
                            'answer_en'=> null,
                            'asset_type' => 'bullet',
                            'emoji' => null,
                            'range_en' => null,
                            'range_bn' => null,
                        ),
                        array(
                            'title_bn' => 'কমপ্লেইন ছাড়া সম্পন্ন',
                            'answer_bn' => 'প্রাপ্ত অর্ডার গুলোর মধ্যে যত গুলো অর্ডার কোন কমপ্লেইন ছাড়া সম্পন্ন হয়েছে।',
                            'title_en' => null,
                            'answer_en'=> null,
                            'asset_type' => 'bullet',
                            'emoji' => null,
                            'range_en' => null,
                            'range_bn' => null,
                        ),
                        array(
                            'title_bn' => 'টাইমলি এক্সেপ্ট',
                            'answer_bn' => 'প্রাপ্ত অর্ডার গুলোর মধ্যে যতগুলো অর্ডার ২ মিনিটের মধ্যে এক্সেপ্ট করতে পেরেছেন।',
                            'title_en' => null,
                            'answer_en'=> null,
                            'asset_type' => 'bullet',
                            'emoji' => null,
                            'range_en' => null,
                            'range_bn' => null,
                        ),
                        array(
                            'title_bn' => 'সময়মত কাজ শুরু',
                            'answer_bn' => ' প্রাপ্ত অর্ডার গুলোর মধ্যে যতগুলো অর্ডার শিডিউল অনুজায়ী শুরু করতে পেরেছেন।',
                            'title_en' => null,
                            'answer_en'=> null,
                            'asset_type' => 'bullet',
                            'emoji' => null,
                            'range_en' => null,
                            'range_bn' => null,
                        ),
                    )
                ),
                array(
                    'question_en' => null,
                    'question_bn' => 'এই বিষয়গুলো কিভাবে আমাদের উপকার করবে?',
                    'list' => array(
                        array(
                            'title_bn' => null,
                            'answer_bn' => 'যখনি তুলনামূলক ভাবে আপনার কোন সার্ভিস এর গুণগত মান কমে যাবে তখনি সেই বিষয়গুলো আপনার সামনে দৃশ্যমান হবে। তখন আপনি উল্লিখিত বিষয়গুলো নিয়ে মান উন্নয়নের জন্য কাজ করতে পারবেন।',
                            'title_en' => null,
                            'answer_en'=> null,
                            'asset_type' => 'none',
                            'emoji' => null,
                            'range_en' => null,
                            'range_bn' => null,
                        ),
                    )
                ),
                array(
                    'question_en' => null,
                    'question_bn' => 'পারফর্মেন্স কিভাবে পরিমাপ করা হবে?',
                    'list' => array(
                        array(
                            'title_bn' => 'খুব ভালো',
                            'answer_bn' => 'আপনার সার্ভিস এর গুণগত মান সর্বোচ্চ পর্যায়ে রাখতে সফল হয়েছেন।',
                            'title_en' => null,
                            'answer_en'=> null,
                            'asset_type' => 'emoji',
                            'emoji' => 'very_good',
                            'range_bn' => '( ৮১% - ১০০% )',
                            'range_en' => '( 81% - 100% )',
                        ),
                        array(
                            'title_bn' => 'খুব ভালো',
                            'answer_bn' => 'আপনার সার্ভিস এর গুণগত মান সর্বোচ্চ পর্যায়ে রাখতে সফল হয়েছেন।',
                            'title_en' => null,
                            'answer_en'=> null,
                            'asset_type' => 'emoji',
                            'emoji' => 'very_good',
                            'range_bn' => '(৮১% - ১০০%)',
                            'range_en' => '(81% - 100%)',
                        ),
                        array(
                            'title_bn' => 'ভালো',
                            'answer_bn' => 'আপনার সার্ভিস এর গুণগত মান কাস্টমার এর প্রত্যাশার কাছাকাছি রয়েছে।',
                            'title_en' => null,
                            'answer_en'=> null,
                            'asset_type' => 'emoji',
                            'emoji' => 'good',
                            'range_bn' => '(৬১% - ৮০%)',
                            'range_en' => '(61% - 80%)',
                        ),
                        array(
                            'title_bn' => 'সন্তোষজনক',
                            'answer_bn' => 'আপনার সার্ভিস এর মান কাস্টমার এর প্রত্যাশার কাছাকাছি নেই।',
                            'title_en' => null,
                            'answer_en'=> null,
                            'asset_type' => 'emoji',
                            'emoji' => 'satisfactory',
                            'range_bn' => '(৪১% - ৬০%)',
                            'range_en' => '(41% - 60%)',
                        ),
                        array(
                            'title_bn' => 'খারাপ',
                            'answer_bn' => 'আপনার সার্ভিস এর মান খারাপ। মান উন্নয়নের জন্য কাজ করতে হবে।',
                            'title_en' => null,
                            'answer_en'=> null,
                            'asset_type' => 'bad',
                            'emoji' => 'good',
                            'range_bn' => '(২১% - ৪০%)',
                            'range_en' => '(21% - 40%)',
                        ),
                        array(
                            'title_bn' => 'খুব খারাপ',
                            'answer_bn' => 'আপনার সার্ভিস এর মান কাস্টমার কে সার্ভ করার উপযোগী নয়। অনুগ্রহ করে মান উন্নয়নের জন্য কাজ করুন।',
                            'title_en' => null,
                            'answer_en'=> null,
                            'asset_type' => 'emoji',
                            'emoji' => 'very_bad',
                            'range_bn' => '(০% - ২০%)',
                            'range_en' => '(0% - 20%)',
                        ),
                    )
                ),
            );
            return api_response($request, $faqs, 200, ['faqs' => $faqs]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }
}
