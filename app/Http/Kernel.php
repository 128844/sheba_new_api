<?php namespace App\Http;

use App\Http\Middleware\AccessTokenMiddleware;
use App\Http\Middleware\AccountingAuthMiddleware;
use App\Http\Middleware\AffiliateAuthMiddleware;
use App\Http\Middleware\ApiRequestMiddleware;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\B2B\EmployeeAuthMiddleware;
use App\Http\Middleware\B2B\OrderMiddleware;
use App\Http\Middleware\B2B\TerminatingMiddleware;
use App\Http\Middleware\BusinessManagerAuthMiddleware;
use App\Http\Middleware\ConcurrentOrderServeCollectMiddleware;
use App\Http\Middleware\CheckUserMigrationRunningMiddleware;
use App\Http\Middleware\ConcurrentRequestMiddleware;
use App\Http\Middleware\Cors2MiddleWare;
use App\Http\Middleware\CriticalAppVersionMiddleware;
use App\Http\Middleware\CustomerAuthMiddleware;
use App\Http\Middleware\CustomerJobAuthMiddleware;
use App\Http\Middleware\DLSApiVersioning;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\ExternalPaymentLinkAuthMiddleware;
use App\Http\Middleware\FormDataMiddleware;
use App\Http\Middleware\GeoAuthMiddleware;
use App\Http\Middleware\IpWhitelistMiddleware;
use App\Http\Middleware\IpWhitelistMiddlewareForMTB;
use App\Http\Middleware\JWT\ResourceAuthMiddleware;
use App\Http\Middleware\JwtAccessTokenMiddleware;
use App\Http\Middleware\JWTAuthentication;
use App\Http\Middleware\JWTAuthMiddleware;
use App\Http\Middleware\ManagerAuthMiddleware;
use App\Http\Middleware\MemberAuthMiddleware;
use App\Http\Middleware\PartnerJobAuthMiddleware;
use App\Http\Middleware\PartnerOrderAuthMiddleware;
use App\Http\Middleware\PartnerResourceAuthMiddleware;
use App\Http\Middleware\PartnerStatusAuthMiddleware;
use App\Http\Middleware\PaymentLinkAuthMiddleware;
use App\Http\Middleware\ProfileAuthMiddleware;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\ResourceJobAuthMiddleware;
use App\Http\Middleware\SetRequestToJwtWhileTesting;
use App\Http\Middleware\Sheba\ShebaNetworkMiddleware;
use App\Http\Middleware\ThrottleRequests;
use App\Http\Middleware\TopUp409DebugMiddleware;
use App\Http\Middleware\TopUpAuthMiddleware;
use App\Http\Middleware\UserMigrationMiddleware;
use App\Http\Middleware\VendorMiddleware;
use App\Http\Middleware\VerifyCsrfToken;
use App\Http\Middleware\XSS;
use App\Http\Middleware\CheckForMaintenanceMode;

use Illuminate\Auth\Middleware\AuthenticateWithBasicAuth;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\Authorize;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Fideloper\Proxy\TrustProxies;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array
     */
    protected $middleware = [
        CheckForMaintenanceMode::class,
        CriticalAppVersionMiddleware::class,
        XSS::class,
        TrustProxies::class,
        SetRequestToJwtWhileTesting::class
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'web' => [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
        ],
        'api' => [
            'throttle:60,1',
        ]
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array
     */
    protected $routeMiddleware = [
        'auth' => Authenticate::class,
        'auth.basic' => AuthenticateWithBasicAuth::class,
        'can' => Authorize::class,
        'guest' => RedirectIfAuthenticated::class,
        'throttle' => ThrottleRequests::class,
        'cors2' => Cors2MiddleWare::class,
        'customer.auth' => CustomerAuthMiddleware::class,
        'customer_job.auth' => CustomerJobAuthMiddleware::class,
        'profile.auth' => ProfileAuthMiddleware::class,
        'affiliate.auth' => AffiliateAuthMiddleware::class,
        'resource.auth' => Middleware\ResourceAuthMiddleware::class,
        'manager.auth' => ManagerAuthMiddleware::class,
        'partner_job.auth' => PartnerJobAuthMiddleware::class,
        'partner_order.auth' => PartnerOrderAuthMiddleware::class,
        'partner_resource.auth' => PartnerResourceAuthMiddleware::class,
        'resource_job.auth' => ResourceJobAuthMiddleware::class,
        'vendor.auth' => VendorMiddleware::class,
        'business_order.auth' => OrderMiddleware::class,
        'geo.auth' => GeoAuthMiddleware::class,
        'loan.version' => DLSApiVersioning::class,
        'external_payment_link.auth' => ExternalPaymentLinkAuthMiddleware::class,
        'business.auth' => BusinessManagerAuthMiddleware::class,
        'member.auth' => MemberAuthMiddleware::class,
        'jwtAuth' => JWTAuthentication::class,//10
        'jwtGlobalAuth' => JWTAuthMiddleware::class,//6
        'topUp.auth' => TopUpAuthMiddleware::class,//1
        'resource.jwt.auth' => ResourceAuthMiddleware::class,//1
        'paymentLink.auth' => PaymentLinkAuthMiddleware::class,//1
        'accessToken' => AccessTokenMiddleware::class,
        'apiRequestLog' => ApiRequestMiddleware::class,
        'shebaServer' => ShebaNetworkMiddleware::class,
        'terminate' => TerminatingMiddleware::class,
        'jwtAccessToken' => JwtAccessTokenMiddleware::class,
        'ip.whitelist' => IpWhitelistMiddleware::class,
        'mtb.ip.whitelist' => IpWhitelistMiddlewareForMTB::class,
        'accounting.auth' => AccountingAuthMiddleware::class,
        'userMigration.auth' => UserMigrationMiddleware::class,
        'userMigration.check_status' => CheckUserMigrationRunningMiddleware::class,
        'partner.status'=> PartnerStatusAuthMiddleware::class,
        'concurrent_request' => ConcurrentRequestMiddleware::class,
        'job.concurrent_serve_collect' => ConcurrentOrderServeCollectMiddleware::class,
        'employee.auth' => EmployeeAuthMiddleware::class,
        'formData'=>FormDataMiddleware::class
    ];
}
