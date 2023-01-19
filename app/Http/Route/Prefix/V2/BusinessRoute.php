<?php

namespace App\Http\Route\Prefix\V2;

class BusinessRoute
{
    public function set($api)
    {
        $api->post('business/login', 'B2b\LoginController@login');
        $api->post('business/contact-us', 'B2b\BusinessesController@contactUs');
        // $api->get('business/test-login', 'B2b\LoginController@generateDummyToken')->middleware('admin.auth');
        $api->get('business/test-push-notification', 'PushSubscriptionController@send');
        $api->get('business/test-email', 'B2b\ProcurementPaymentRequestController@testEmail');
        $api->post('business/register', 'B2b\RegistrationController@registerV2');
        $api->group(['prefix' => 'businesses/tenders'], function ($api) {
            $api->get('/', 'B2b\ProcurementController@tenders');
            $api->get('filter-options', 'B2b\ProcurementController@filterOptions');
            $api->get('landings', 'B2b\ProcurementController@landings');
            $api->group(['prefix' => '{tender}'], function ($api) {
                $api->get('/', 'B2b\ProcurementController@tenderShow');
                $api->group(['prefix' => 'proposal'], function ($api) {
                    $api->get('/', 'B2b\ProcurementController@tenderProposalEdit');
                    $api->post('/', 'B2b\ProcurementController@tenderProposalStore');
                    $api->group(['prefix' => '{proposal}'], function ($api) {
                        $api->get('/', 'B2b\ProcurementController@proposalDetail');
                        $api->get('/send-pin', 'B2b\ProposalController@sendPin');
                        $api->get('/work-order/download', 'B2b\ProcurementController@downloadWorkOrder');
                        $api->post('/', 'B2b\ProposalController@takeAction');
                    });
                });
            });
        });
        $api->group(['prefix' => 'businesses', 'middleware' => ['business.auth']], function ($api) {
            $api->group(['prefix' => '{business}'], function ($api) {
                $api->get('refresh-token', 'Profile\ProfileController@refreshToken');
                $api->get('members', 'B2b\MemberController@index');
                $api->post('/invite', 'B2b\BusinessesController@inviteVendors');
                $api->get('/vendors', 'B2b\BusinessesController@getVendorsList');
                $api->get('/banks', 'B2b\BusinessesController@getBanks');
                $api->get('/vendors/{vendor}/info', 'B2b\BusinessesController@getVendorInfo');
                $api->get('/vendors/{vendor}/resource-info', 'B2b\BusinessesController@getVendorAdminInfo');
                $api->post('orders', 'B2b\OrderController@placeOrder');
                $api->get('trips', 'B2b\TripSchedulerController@getList');
                $api->post('promotions/add', 'B2b\OrderController@applyPromo');
                $api->get('/transactions', 'B2b\BusinessTransactionController@index');
                $api->get('/dept-role', 'B2b\CoWorkerController@departmentRole');
                $api->get('/salary-alert', 'B2b\BusinessesController@salaryAlert');

                $api->group(['prefix' => 'departments'], function ($api) {
                    $api->post('/', 'B2b\DepartmentController@store');
                    $api->get('/', 'B2b\DepartmentController@index');
                    $api->get('/get-for-filter', 'B2b\DepartmentController@getForFilter');
                    $api->group(['prefix' => '{department}'], function ($api) {
                        $api->post('/', 'B2b\DepartmentController@update');
                        $api->delete('/', 'B2b\DepartmentController@destroy');
                    });
                });

                $api->post('/roles', 'B2b\CoWorkerController@addBusinessRole');
                $api->get('/sms-templates', 'B2b\BusinessSmsTemplateController@index');
                $api->get('/test-sms', 'B2b\BusinessSmsTemplateController@sendSms');
                $api->post('/sms-templates/{sms}', 'B2b\BusinessSmsTemplateController@update');
                $api->get('/sms-templates/{sms}', 'B2b\BusinessSmsTemplateController@show');
                $api->post('/download-transactions', 'B2b\BusinessesController@downloadTransactionReport');
                $api->group(['prefix' => 'members'], function ($api) {
                    $api->group(['prefix' => '{member}'], function ($api) {
                        $api->get('attendances', 'B2b\AttendanceController@showStat');
                        $api->post('info', 'B2b\MemberController@updateMemberInfo');
                    });
                });
                $api->group(['prefix' => 'attendances'], function ($api) {
                    $api->get('daily', 'B2b\AttendanceController@getDailyStats');
                    $api->get('monthly', 'B2b\AttendanceController@getMonthlyStats');
                    $api->get('monthly-list', 'B2b\AttendanceController@getMonthlyStatsV2');
                    $api->post('reconciliation', 'B2b\AttendanceReconciliationController@create');
                    $api->post('bulk-reconciliation', 'B2b\AttendanceReconciliationController@bulkReconciliation');
                });
                $api->group(['prefix' => 'office-time'], function ($api) {
                    $api->get('/', 'B2b\AttendanceController@getOfficeTime');
                    $api->post('/update', 'B2b\AttendanceController@updateOfficeTime');
                });
                $api->group(['prefix' => 'attendance-setting'], function ($api) {
                    $api->get('/', 'B2b\AttendanceController@getAttendanceSetting');
                    $api->post('/update', 'B2b\AttendanceController@updateAttendanceSetting');
                });
                $api->group(['prefix' => 'half-day-setting'], function ($api) {
                    $api->post('/', 'B2b\AttendanceController@updateHalfDaySetting');
                });
                $api->group(['prefix' => 'holidays'], function ($api) {
                    $api->get('/', 'B2b\AttendanceController@getHolidays');
                    $api->get('all-dates', 'B2b\AttendanceController@getAllHolidayDates');
                    $api->post('/', 'B2b\AttendanceController@storeHoliday');
                    $api->group(['prefix' => '{holiday}'], function ($api) {
                        $api->post('/', 'B2b\AttendanceController@update');
                        $api->delete('/', 'B2b\AttendanceController@destroy');
                    });
                });
                $api->group(['prefix' => 'employees'], function ($api) {
                    $api->get('/', 'B2b\CoWorkerController@index');
                    $api->post('/', 'B2b\CoWorkerController@basicInfoStore');
                    $api->get('/roles', 'B2b\CoWorkerController@getRoles');
                    $api->post('/change-status', 'B2b\CoWorkerStatusController@bulkStatusUpdate');
                    $api->post('/invite', 'B2b\CoWorkerInviteController@sendInvitation');
                    $api->post('/single-invite', 'B2b\CoWorkerInviteController@sendSingleInvitation');
                    $api->get('/report', 'B2b\CoWorkerController@downloadEmployeesReport');
                    $api->get('/gross-salary-report', 'B2b\CoWorkerGrossSalaryController@grossSalaryReport');
                    $api->post('/gross-salary-upload', 'B2b\CoWorkerGrossSalaryController@bulkGrossSalaryUpload');
                    $api->get('/bkash-number-report', 'B2b\CoWorkerBkashController@bulkBakshInfoReport');
                    $api->post('/bkash-number-upload', 'B2b\CoWorkerBkashController@bulkBakshInfoUpload');

                    $api->group(['prefix' => 'additional'], function ($api) {
                        $api->get('/', 'B2b\AdditionalInformationController@index');
                        $api->post('/', 'B2b\AdditionalInformationController@upsert');
                    });

                    $api->group(['prefix' => '{employee}'], function ($api) {
                        $api->post('/basic-info', 'B2b\CoWorkerController@basicInfoEdit');
                        $api->post('/official-info', 'B2b\CoWorkerController@officialInfoEdit');
                        $api->post('/personal-info', 'B2b\CoWorkerController@personalInfoEdit');
                        $api->post('/financial-info', 'B2b\CoWorkerController@financialInfoEdit');
                        $api->post('/emergency-info', 'B2b\CoWorkerController@emergencyInfoEdit');
                        $api->post('/salary-info', 'B2b\CoWorkerController@salaryInfoEdit');
                        $api->post('/status', 'B2b\CoWorkerStatusController@statusUpdate');
                        $api->post('/active', 'B2b\CoWorkerStatusController@activeFromInvited');
                        $api->get('/', 'B2b\CoWorkerController@show');
                        $api->post('/', 'B2b\CoWorkerController@update');
                        $api->get('/expense/pdf', 'B2b\CoWorkerController@show');
                        $api->get('/salary-certificate/pdf', 'B2b\CoWorkerController@salaryCertificatePdf');
                        $api->group(['prefix' => 'additional-sections'], function ($api) {
                            $api->get('/', 'B2b\AdditionalInformationController@sections');
                            $api->group(['prefix' => '{section_id}'], function ($api) {
                                $api->get('/', 'B2b\AdditionalInformationController@sectionDetails');
                                $api->post('/', 'B2b\AdditionalInformationController@updateData');
                            });
                        });
                    });
                });
                $api->group(['prefix' => 'leaves'], function ($api) {
                    $api->post('/adjustment', 'B2b\LeaveAdjustmentController@leaveAdjustment');
                    $api->post('/bulk-adjustment', 'B2b\LeaveAdjustmentController@bulkLeaveAdjustment');
                    $api->get('/generate-adjustment-excel', 'B2b\LeaveAdjustmentController@generateAdjustmentExcel');
                    $api->get('/reject-reasons', 'B2b\LeaveController@rejectReasons');
                    $api->group(['prefix' => 'approval-requests'], function ($api) {
                        $api->get('/lists', 'B2b\LeaveController@index');
                        $api->get('/leave-history/{business_member_id}', 'B2b\LeaveController@leaveHistory');
                        $api->group(['prefix' => '{approval_request}'], function ($api) {
                            $api->get('/', 'B2b\LeaveController@show');
                        });
                        $api->post('/change-status-by-super-admin', 'B2b\LeaveController@statusUpdateBySuperAdmin');
                        $api->post('/status', 'B2b\LeaveController@updateStatus');
                        $api->post('/leave-info-edit', 'B2b\LeaveController@infoUpdateBySuperAdmin');
                    });
                    $api->group(['prefix' => 'balance'], function ($api) {
                        $api->get('/lists', 'B2b\LeaveController@allLeaveBalance');
                        $api->get('/{balance}', 'B2b\LeaveController@leaveBalanceDetails');
                        $api->get('remaining/{balance}', 'B2b\LeaveController@leaveBalanceRemaining');
                    });
                    $api->group(['prefix' => 'super-admins'], function ($api) {
                        $api->get('/', 'B2b\LeaveController@getSuperAdmins');
                    });
                    $api->group(['prefix' => 'settings'], function ($api) {
                        $api->get('/', 'B2b\LeaveSettingsController@index');
                        $api->post('/', 'B2b\LeaveSettingsController@store');

                        $api->group(['prefix' => '{setting}'], function ($api) {
                            $api->post('update', 'B2b\LeaveSettingsController@update');
                            $api->delete('delete', 'B2b\LeaveSettingsController@delete');
                        });
                        $api->group(['prefix' => 'others'], function ($api) {
                            $api->get('/', 'B2b\LeaveSettingsController@othersInfo');
                            $api->post('/', 'B2b\LeaveSettingsController@othersUpdate');
                        });
                    });
                    $api->group(['prefix' => 'prorate'], function ($api) {
                        $api->post('/', 'B2b\ProrateController@store');
                        $api->get('/', 'B2b\ProrateController@index');
                        $api->post('/delete', 'B2b\ProrateController@delete');
                        $api->post('/run-prorate', 'B2b\ProrateController@runAutoProrate');
                        $api->post('/leave-type-auto-prorate', 'B2b\ProrateController@leaveTypeAutoProrate');
                        $api->group(['prefix' => '{prorate}'], function ($api) {
                            $api->post('/', 'B2b\ProrateController@edit');
                        });
                    });
                });
                $api->group(['prefix' => 'orders'], function ($api) {
                    $api->get('/', 'B2b\OrderController@index');
                    $api->group(['prefix' => '{order}', 'middleware' => ['business_order.auth']], function ($api) {
                        $api->get('/', 'B2b\OrderController@show');
                        $api->post('/reviews', 'B2b\ReviewController@store');
                        $api->post('/rates', 'B2b\RateController@store');
                        $api->get('/rates', 'B2b\RateController@index');
                        $api->get('bills/clear', 'B2b\OrderController@clearBills');
                        $api->get('bills', 'B2b\OrderController@getBills');
                    });
                });
                $api->group(['prefix' => 'form-templates'], function ($api) {
                    $api->get('/', 'B2b\FormTemplateController@index');
                    $api->post('/', 'B2b\FormTemplateController@store');
                    $api->group(['prefix' => '{form_template}'], function ($api) {
                        $api->get('/', 'B2b\FormTemplateController@get');
                        $api->post('/', 'B2b\FormTemplateController@edit');
                        $api->group(['prefix' => 'items'], function ($api) {
                            $api->post('/', 'B2b\FormTemplateItemController@store');
                            $api->group(['prefix' => '{item}'], function ($api) {
                                $api->post('/', 'B2b\FormTemplateItemController@edit');
                                $api->delete('/', 'B2b\FormTemplateItemController@destroy');
                            });
                        });
                    });
                });
                $api->group(['prefix' => 'inspections'], function ($api) {
                    $api->get('/', 'B2b\InspectionController@index');
                    $api->post('/', 'B2b\InspectionController@store');
                    $api->get('/forms', 'B2b\InspectionController@inspectionForms');
                    $api->group(['prefix' => '{inspection}'], function ($api) {
                        $api->get('/', 'B2b\InspectionController@show');
                        $api->get('list', 'B2b\InspectionController@getChildrenInspections');
                        $api->post('/', 'B2b\InspectionController@edit');
                        $api->post('submit', 'B2b\InspectionController@submit');
                        $api->group(['prefix' => 'items'], function ($api) {
                            $api->post('/', 'B2b\InspectionItemController@store');
                            $api->group(['prefix' => '{item}'], function ($api) {
                                $api->post('/', 'B2b\InspectionItemController@edit');
                                $api->post('acknowledge', 'B2b\InspectionItemController@acknowledge');
                                $api->delete('/', 'B2b\InspectionItemController@destroy');
                            });
                        });
                    });
                });
                $api->group(['prefix' => 'procurements'], function ($api) {
                    $api->post('/', 'B2b\ProcurementController@store');
                    $api->get('/create', 'B2b\ProcurementController@create');
                    $api->get('/tags', 'B2b\ProcurementController@getTags');
                    $api->get('/orders', 'B2b\ProcurementController@procurementOrders');
                    $api->group(['prefix' => '{procurement}'], function ($api) {
                        $api->get('/', 'B2b\ProcurementController@show');
                        $api->get('/download', 'B2b\ProcurementController@downloadPdf');
                        $api->post('adjust-payment', 'B2b\ProcurementPaymentController@adjustPayment');
                        $api->group(['prefix' => 'comments'], function ($api) {
                            $api->post('/', 'CommentController@storeComments');
                            $api->get('/', 'CommentController@getComments');
                        });
                        $api->group(['prefix' => 'attachments'], function ($api) {
                            $api->post('/', 'AttachmentController@storeAttachment');
                            $api->get('/', 'AttachmentController@getAttachments');
                        });
                        $api->post('/update-item', 'B2b\ProcurementController@updateItem');
                        $api->get('/invitations', 'B2b\ProcurementController@invitedPartners');
                        $api->get('/bill', 'B2b\ProcurementController@orderBill');
                        $api->post('invitations', 'B2b\ProcurementController@sendInvitation');
                        $api->post('publish', 'B2b\ProcurementController@updateStatus');
                        $api->post('publication-status', 'B2b\ProcurementController@updatePublicationStatus');
                        $api->post('description', 'B2b\ProcurementController@updateDescription');
                        $api->post('general', 'B2b\ProcurementController@updateGeneral');
                        $api->post('basic', 'B2b\ProcurementController@updateBasic');
                        $api->post('attachments', 'B2b\ProcurementController@updateAttachments');
                        $api->get('/bid-history', 'B2b\BidController@getBidHistory');
                        $api->get('/hiring-history', 'B2b\BidController@getHiringHistory');
                        $api->get('bills/clear', 'B2b\ProcurementController@clearBills');
                        $api->get('/timeline', 'B2b\ProcurementController@orderTimeline');
                        $api->group(['prefix' => 'bids'], function ($api) {
                            $api->get('/', 'B2b\BidController@index');
                            $api->group(['prefix' => '{bid}'], function ($api) {
                                $api->get('/', 'B2b\ProcurementController@showProcurementOrder');
                                $api->get('/work-order', 'B2b\ProcurementController@workOrder');
                                $api->get('/work-order/download', 'B2b\ProcurementController@downloadWorkOrder');
                                $api->group(['prefix' => 'payment-requests'], function ($api) {
                                    $api->get('/', 'B2b\ProcurementPaymentRequestController@index');
                                    $api->group(['prefix' => '{request}'], function ($api) {
                                        $api->get('/', 'B2b\ProcurementPaymentRequestController@show');
                                        $api->post('/', 'B2b\ProcurementPaymentRequestController@updatePaymentRequest');
                                        $api->get('download', 'B2b\ProcurementPaymentRequestController@downloadPdf');
                                    });
                                });
                            });
                        });
                    });
                    $api->get('/', 'B2b\ProcurementController@index');
                });
                $api->group(['prefix' => 'bids'], function ($api) {
                    $api->group(['prefix' => '{bid}'], function ($api) {
                        $api->get('/', 'B2b\BidController@show');
                        $api->get('/download', 'B2b\BidController@downloadPdf');
                        $api->post('/', 'B2b\BidController@updateFavourite');
                        $api->post('hire', 'B2b\BidController@sendHireRequest');
                        $api->group(['prefix' => 'comments'], function ($api) {
                            $api->post('/', 'CommentController@storeComments');
                            $api->get('/', 'CommentController@getComments');
                        });
                        $api->group(['prefix' => 'attachments'], function ($api) {
                            $api->post('/', 'AttachmentController@storeAttachment');
                            $api->get('/', 'AttachmentController@getAttachments');
                        });
                    });
                });
                $api->group(['prefix' => 'purchase-requests'], function ($api) {
                    $api->get('/', 'B2b\PurchaseRequestController@index');
                    $api->post('/', 'B2b\PurchaseRequestController@store');

                    $api->group(['prefix' => '{purchase_request}'], function ($api) {
                        $api->get('/', 'B2b\PurchaseRequestController@show');
                        $api->post('/change-status', 'B2b\PurchaseRequestController@changeStatus');
                        $api->post('/member-approval-request', 'B2b\PurchaseRequestController@memberApprovalRequest');
                    });

                    $api->group(['prefix' => 'forms'], function ($api) {
                        $api->get('/', 'B2b\PurchaseRequestController@forms');
                    });
                });
                $api->group(['prefix' => 'fuel-logs'], function ($api) {
                    $api->get('/', 'B2b\FuelLogController@index');
                    $api->post('/', 'B2b\FuelLogController@store');
                    $api->post('{fuel_log}', 'B2b\FuelLogController@store');
                });
                $api->group(['prefix' => 'inspection-items'], function ($api) {
                    $api->get('/', 'B2b\InspectionItemController@index');
                    $api->get('/{item}', 'B2b\InspectionItemController@show');
                });
                $api->group(['prefix' => 'issues'], function ($api) {
                    $api->get('/', 'B2b\IssueController@index');
                    $api->post('/', 'B2b\IssueController@store');
                    $api->get('/{issue}', 'B2b\IssueController@show');
                    $api->post('{issue}/close', 'B2b\IssueController@close');
                    $api->post('/{issue}/attachments', 'B2b\IssueController@storeAttachment');
                    $api->get('/{issue}/attachments', 'B2b\IssueController@getAttachments');
                    $api->post('/{issue}/comments', 'B2b\IssueController@storeComment');
                    $api->get('/{issue}/comments', 'B2b\IssueController@getComments');
                });
                $api->group(['prefix' => 'fuel-logs'], function ($api) {
                    $api->get('/', 'B2b\FuelLogController@index');
                    $api->get('/{log}', 'B2b\FuelLogController@show');
                    $api->post('/{log}/attachments', 'B2b\FuelLogController@storeAttachment');
                    $api->get('/{log}/attachments', 'B2b\FuelLogController@getAttachments');
                    $api->post('/{log}/comments', 'B2b\FuelLogController@storeComment');
                    $api->get('/{log}/comments', 'B2b\FuelLogController@getComments');
                });
                $api->group(['prefix' => 'supports'], function ($api) {
                    $api->get('/', 'B2b\SupportController@index');
                    $api->group(['prefix' => '{support}'], function ($api) {
                        $api->get('/', 'B2b\SupportController@show');
                    });
                });
                $api->group(['prefix' => 'notifications'], function ($api) {
                    $api->get('/', 'B2b\BusinessesController@getNotifications');
                    $api->post('/{notification}/seen', 'B2b\BusinessesController@notificationSeen');
                });
                $api->group(['prefix' => 'vendors'], function ($api) {
                    $api->get('/', 'B2b\BusinessesController@getVendorsList');
                    $api->post('/', 'B2b\VendorController@store');
                    $api->post('/bulk-store', 'B2b\VendorController@bulkStore');
                    $api->group(['prefix' => '{vendor}'], function ($api) {
                        $api->get('/info', 'B2b\BusinessesController@getVendorInfo');
                        $api->post('/active', 'B2b\VendorController@activeInactive');
                    });
                });
                $api->group(['prefix' => 'subscription-orders'], function ($api) {
                    $api->post('/', 'B2b\OrderController@placeSubscriptionOrder');
                    $api->get('/', 'B2b\SubscriptionOrderController@index');
                    $api->get('/{order}', 'B2b\SubscriptionOrderController@show');
                    $api->get('/{order}/invoice', 'B2b\SubscriptionOrderController@orderInvoice');
                    $api->group(['prefix' => '{subscription_order}'], function ($api) {
                        $api->get('bills/clear', 'B2b\SubscriptionOrderController@clearPayment');
                    });
                });
                $api->group(['prefix' => 'announcements'], function ($api) {
                    $api->get('/', 'B2b\AnnouncementController@index');
                    $api->post('/', 'B2b\AnnouncementController@store');
                    $api->group(['prefix' => '{announcement}'], function ($api) {
                        $api->put('/', 'B2b\AnnouncementController@update');
                        $api->get('/', 'B2b\AnnouncementController@show');
                    });
                });
                $api->group(['prefix' => 'expense'], function ($api) {
                    $api->get('/', 'B2b\ExpenseController@index');
                    $api->get('/download-pdf', 'B2b\ExpenseController@downloadPdf');
                    $api->get('/download-expense-report', 'B2b\ExpenseController@downloadExpenseReport');
                    $api->get('/filter-month/{business_member_id}', 'B2b\ExpenseController@filterMonth');
                    $api->group(['prefix' => '{expense}'], function ($api) {
                        $api->get('/', 'B2b\ExpenseController@show');
                        $api->post('/', 'B2b\ExpenseController@update');
                        $api->delete('/', 'B2b\ExpenseController@delete');
                    });
                });
                $api->group(['prefix' => 'approval-flows'], function ($api) {
                    $api->get('/', 'B2b\ApprovalFlowController@index');
                    $api->post('/', 'B2b\ApprovalFlowController@store');
                    $api->get('/types', 'B2b\ApprovalFlowController@getTypes');
                    $api->get('{approval_flow}', 'B2b\ApprovalFlowController@show');
                    $api->post('{approval_flow}', 'B2b\ApprovalFlowController@update');
                });
                $api->group(['prefix' => 'pay-run'], function ($api) {
                    $api->post('/update', 'B2b\PayRunController@bulkUpdate');
                    $api->post('/disburse/{summary_id}', 'B2b\PayRunController@disburse');
                    $api->get('/pending-months', 'B2b\PayRunController@pendingMonths');
                });
                $api->group(['prefix' => 'pay-report'], function ($api) {
                    $api->get('/bkash-salary-report/{id}', 'B2b\PayReportController@bkashSalaryReport');
                    $api->get('/last-disbursed-month', 'B2b\PayReportController@lastDisbursedMonth');
                    $api->get('/{id}', 'B2b\PayReportController@show');
                });
                $api->group(['prefix' => 'payroll'], function ($api) {
                    $api->get('/components', 'B2b\PayrollController@getPayrollComponents');
                    $api->get('/settings', 'B2b\PayrollController@getPayrollSettings');
                    $api->post('/pay-schedule/{id}', 'B2b\PayrollController@updatePaySchedule');
                    $api->post('/salary-breakdown/{id}', 'B2b\PayrollController@updateSalaryBreakdown');
                    $api->post('/component/{payroll_settings}', 'B2b\PayrollController@addComponent');
                    $api->post('/gross-component/{payroll_settings}', 'B2b\PayrollController@grossComponentAddUpdate');
                    $api->get('/pay-day', 'B2b\PayrollController@checkPayDayConflicting');
                    $api->get('/monthly-pay-cycle', 'B2b\PayrollController@getMonthlyPayCycle');
                    $api->get('/tax-report', 'B2b\TaxHistoryController@index');
                    $api->post('show-tax-report-download-banner', 'B2b\TaxHistoryController@updateReportShowBanner');
                    $api->get('download-tax-certificate/{business_member_id}/tax-report/{id}', 'B2b\TaxHistoryController@downloadBusinessMemberTaxCertificate');
                });
                $api->group(['prefix' => 'pay-report-summary'], function ($api) {
                    $api->get('/', 'B2b\BusinessPayReportSummaryController@index');
                    $api->get('pay-report/{business_payslip_id}', 'B2b\PayReportController@index');
                    $api->get('pay-run/{business_payslip_id}', 'B2b\PayRunController@index');
                });
                $api->group(['prefix' => 'employee-visit'], function ($api) {
                    $api->post('settings', 'B2b\VisitSettingController@settings');
                    $api->get('settings', 'B2b\VisitSettingController@getSettings');
                    $api->get('team-visits', 'B2b\VisitController@getTeamVisits');
                    $api->get('my-visits', 'B2b\VisitController@getMyVisits');
                    $api->get('/{id}', 'B2b\VisitController@show');
                });
                $api->group(['prefix' => 'shift'], function ($api) {
                    $api->get('/', 'B2b\ShiftSettingController@index');
                    $api->post('/', 'B2b\ShiftSettingController@create');
                    $api->get('/color', 'B2b\ShiftSettingController@getColor');
                    $api->group(['prefix' => '{id}'], function ($api) {
                        $api->delete('/', 'B2b\ShiftSettingController@delete');
                        $api->get('/', 'B2b\ShiftSettingController@details');
                        $api->post('/color', 'B2b\ShiftSettingController@updateColor');
                        $api->post('/update', 'B2b\ShiftSettingController@updateShift');
                    });
                });
                $api->group(['prefix' => 'shift-calender'], function ($api) {
                    $api->get('/', 'B2b\ShiftAssignmentController@index');
                    $api->get('/dashboard', 'B2b\ShiftAssignmentController@dashboard');
                    $api->group(['prefix' => '{id}'], function ($api) {
                        $api->get('/', 'B2b\ShiftAssignmentController@details');
                        $api->post('/assign-shift', 'B2b\ShiftAssignmentController@assignShift');
                        $api->post('/assign-general-attendance', 'B2b\ShiftAssignmentController@assignGeneralAttendance');
                        $api->post('/unassign-shift', 'B2b\ShiftAssignmentController@unassignShift');
                    });
                });

                $api->group(['prefix' => 'live-tracking'], function ($api) {
                    $api->post('setting', 'B2b\TrackingController@settingsAction');
                    $api->get('setting', 'B2b\TrackingController@getSettings');
                    $api->post('employee-setting', 'B2b\TrackingController@employeeTrackingAction');
                    $api->get('employee-lists', 'B2b\TrackingController@employeeLists');
                    $api->get('logs', 'B2b\TrackingController@getChangesLogs');
                    $api->get('/', 'B2b\TrackingController@index');
                    $api->group(['prefix' => '{id}'], function ($api) {
                        $api->get('/', 'B2b\TrackingController@getTrackingDetails');
                        $api->get('last-track', 'B2b\TrackingController@lastTrackedDate');
                        $api->get('download', 'B2b\TrackingController@downloadLiveTrackingReport');
                    });
                });
            });
        });
        $api->group(['prefix' => 'members', 'middleware' => ['member.auth']], function ($api) {
            $api->group(['prefix' => '{member}'], function ($api) {
                $api->get('info', 'B2b\MemberController@getMemberInfo');
                $api->get('get-business-info', 'B2b\MemberController@getBusinessInfo');
                $api->post('update-business-info', 'B2b\MemberController@updateBusinessInfo');
                $api->post('/attachments', 'B2b\MemberController@storeAttachment');
                $api->get('/attachments', 'B2b\MemberController@getAttachments');

                $api->group(['prefix' => 'vehicles'], function ($api) {
                    $api->post('/', 'B2b\VehiclesController@store');
                    $api->post('/bulk-store', 'B2b\VehiclesController@bulkStore');
                    $api->get('/', 'B2b\VehiclesController@index');
                    $api->group(['prefix' => '{vehicle}'], function ($api) {
                        $api->post('/', 'B2b\VehiclesController@update');
                        $api->get('/general-info', 'B2b\VehiclesController@getVehicleGeneralInfo');
                        $api->post('/general-info', 'B2b\VehiclesController@updateVehicleGeneralInfo');
                        $api->get('/registration-info', 'B2b\VehiclesController@getVehicleRegistrationInfo');
                        $api->post('/registration-info', 'B2b\VehiclesController@updateVehicleRegistrationInfo');
                        $api->get('/specs', 'B2b\VehiclesController@getVehicleSpecs');
                        $api->get('handlers', 'B2b\VehiclesController@getVehicleHandlers');
                        $api->put('/driver', 'B2b\VehiclesController@unTagVehicleDriver');
                        $api->post('/specs', 'B2b\VehiclesController@updateVehicleSpecs');
                        $api->post('/specs', 'B2b\VehiclesController@updateVehicleSpecs');
                        $api->get('/recent-assignment', 'B2b\VehiclesController@getVehicleRecentAssignment');
                    });
                });
                $api->group(['prefix' => 'drivers'], function ($api) {
                    $api->post('/', 'B2b\DriverController@store');
                    $api->post('/bulk-store', 'B2b\DriverController@bulkStore');
                    $api->get('/', 'B2b\DriverController@index');
                    $api->group(['prefix' => '{driver}'], function ($api) {
                        $api->post('/', 'B2b\DriverController@update');
                        $api->get('/general-info', 'B2b\DriverController@getDriverGeneralInfo');
                        $api->post('/general-info', 'B2b\DriverController@updateDriverGeneralInfo');
                        $api->get('/license-info', 'B2b\DriverController@getDriverLicenseInfo');
                        $api->post('/license-info', 'B2b\DriverController@updateDriverLicenseInfo');
                        $api->get('/contract-info', 'B2b\DriverController@getDriverContractInfo');
                        $api->post('/contract-info', 'B2b\DriverController@updateDriverContractInfo');
                        $api->get('/experience-info', 'B2b\DriverController@getDriverExperienceInfo');
                        $api->post('/experience-info', 'B2b\DriverController@updateDriverExperienceInfo');
                        $api->get('/documents', 'B2b\DriverController@getDriverDocuments');
                        $api->post('/documents', 'B2b\DriverController@updateDriverDocuments');
                        $api->post('update-picture', 'B2b\DriverController@updatePicture');
                        $api->get('/recent-assignment', 'B2b\DriverController@getDriverRecentAssignment');
                    });
                });
                $api->group(['prefix' => 'trips'], function ($api) {
                    $api->get('/', 'B2b\TripRequestController@getTrips');
                    $api->post('/', 'B2b\TripRequestController@createTrip');
                    $api->group(['prefix' => '{trip}'], function ($api) {
                        $api->get('/', 'B2b\TripRequestController@tripInfo');
                        $api->post('comments', 'B2b\TripRequestController@commentOnTrip');
                    });
                });
                $api->group(['prefix' => 'trip-requests'], function ($api) {
                    $api->get('/', 'B2b\TripRequestController@getTripRequests');
                    $api->post('/', 'B2b\TripRequestController@createTripRequests');
                    $api->group(['prefix' => '{trip_requests}'], function ($api) {
                        $api->get('/', 'B2b\TripRequestController@tripRequestInfo');
                        $api->post('/comments', 'B2b\TripRequestController@commentOnTripRequest');
                    });
                });
                $api->get('/fleet-mail', 'B2b\TripRequestController@fleetMail');
                $api->group(['prefix' => 'trip-request-approval'], function ($api) {
                    $api->get('/', 'B2b\TripRequestApprovalController@index');
                    $api->post('{approval}/change-status', 'B2b\TripRequestApprovalController@statusUpdate');
                });
                $api->group(['prefix' => 'inspections'], function ($api) {
                    $api->get('/', 'B2b\InspectionController@individualInspection');
                });
                $api->group(['prefix' => 'supports'], function ($api) {
                    $api->get('/', 'B2b\SupportController@index');
                    $api->group(['prefix' => '{support}'], function ($api) {
                        $api->post('resolve', 'B2b\SupportController@resolve');
                        $api->get('/', 'B2b\SupportController@show');
                    });
                });
            });
        });
    }
}
