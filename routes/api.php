<?php

use App\Http\Controllers\API\ForgotPasswordController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\RoleController;
use App\Http\Controllers\API\PermissionController;
use App\Http\Controllers\Backend\AdvancePaymentController;
use App\Http\Controllers\Backend\BankVoucherController;
use App\Http\Controllers\Backend\BlacklistPeriodController;
use App\Http\Controllers\Backend\ChangeMeterController;
use App\Http\Controllers\Backend\CompanyController;
use App\Http\Controllers\Backend\DisabilityDiscountController;
use App\Http\Controllers\Backend\DiscountAndFineController;
use App\Http\Controllers\Backend\DownloadController;
use App\Http\Controllers\Backend\FileUploadController;
use App\Http\Controllers\Backend\FineController;
use App\Http\Controllers\Backend\GenerateCodeController;
use App\Http\Controllers\Backend\MahasulReceiptEntryController;
use App\Http\Controllers\Backend\MasterSetupController;
use App\Http\Controllers\Backend\MeasureUnitController;
use App\Http\Controllers\Backend\MemberEntryController;
use App\Http\Controllers\Backend\MeterDepositTransactionController;
use App\Http\Controllers\Backend\MeterInsuranceController;
use App\Http\Controllers\Backend\MeterIssueController;
use App\Http\Controllers\Backend\MeterReadingEntryController;
use App\Http\Controllers\Backend\NameTransferController;
use App\Http\Controllers\Backend\NEAPaymentEntryController;
use App\Http\Controllers\Backend\NEAPurchaseController;
use App\Http\Controllers\Backend\NonMemberPaymentController;
use App\Http\Controllers\Backend\OpeningMahasulBalanceEntryController;
use App\Http\Controllers\Backend\OpeningMeterDepositEntryController;
use App\Http\Controllers\Backend\OtherIncomeSetupController;
use App\Http\Controllers\Backend\OtherIncomeReceiptController;
use App\Http\Controllers\Backend\FiscalYearController;
use App\Http\Controllers\Backend\RateAndCapacityController;
use App\Http\Controllers\Backend\ShareOpeningEntryController;
use App\Http\Controllers\Backend\ShareTransactionController;
use App\Http\Controllers\Backend\SyncController;
use App\Http\Controllers\Backend\TariffSetupController;
use App\Http\Controllers\Backend\UpgradeMeterCapacityController;
use App\Http\Controllers\DateConversionController;
use App\Http\Controllers\Information\DepositDetailsController;
use App\Http\Controllers\Information\MahsulDetailsController;
use App\Http\Controllers\Information\MemberDetailsController;
use App\Http\Controllers\Information\MeterReadingDetailsController;
use App\Http\Controllers\Information\OtherIncomeDetailsController;
use App\Http\Controllers\Information\ShareDetailsController;
use App\Http\Controllers\Inventory\AccountGroupController;
use App\Http\Controllers\Inventory\AccountHeadController;
use App\Http\Controllers\Inventory\FixedAssetAccountController;
use App\Http\Controllers\Inventory\FixedAssetGroupController;
use App\Http\Controllers\Inventory\MainGroupController;
use App\Http\Controllers\Inventory\SubGroupController;
use App\Http\Controllers\Inventory\VoucherSummaryController;
use App\Http\Controllers\Report\AbsentLedgerController;
use App\Http\Controllers\Report\PresentLedgerController;
use App\Http\Controllers\Report\ChangeMeterReportController;

use App\Http\Controllers\Report\DepositReportController;
use App\Http\Controllers\Report\IncomeHeadReportController;
use App\Http\Controllers\Report\MemberReportController;
use App\Http\Controllers\Report\MeterDepositReportController;
use App\Http\Controllers\Report\MahasulReceiptReportController;
use App\Http\Controllers\Report\MeterIssueReportController;
use App\Http\Controllers\Report\MeterReadingReportController;
use App\Http\Controllers\Report\NameTransferReportController;
use App\Http\Controllers\Report\NeaLeakageReportController;
use App\Http\Controllers\Report\NeaPurchaseReportController;
use App\Http\Controllers\Report\CustomerDueReportController;
use App\Http\Controllers\Report\AdvanceReportController;
use App\Http\Controllers\Report\NeaReportController;
use App\Http\Controllers\Report\OffsetReportController;
use App\Http\Controllers\Report\OpeningMahasulBalanceReportController;
use App\Http\Controllers\Report\OpeningMeterDepositReportController;
use App\Http\Controllers\Report\VoucherReportlistController;
use App\Imports\MeterIssueImport;
use App\Models\OtherIncomeReceipt;
use App\Http\Controllers\Backend\WiringPersonController;
use App\Http\Controllers\Backend\ProvinceController;
use App\Http\Controllers\Backend\DistrictController;
use App\Http\Controllers\Backend\MunicipalityController;
use App\Http\Controllers\Backend\CronJobController;
use App\Http\Controllers\Backend\RebateDiscountController;
use App\Http\Controllers\Backend\RoleMenuPermissionController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Backend\CustomerTrasnsactionController;
use App\Http\Controllers\Backend\EmailController;
use App\Http\Controllers\Backend\ExpenseAndReceivableTrackerController;
use App\Http\Controllers\Backend\JournalVoucherController;
use App\Http\Controllers\Backend\OpeningBalanceController;
use App\Http\Controllers\Backend\OpeningMahasulFineSetupController;
use App\Http\Controllers\Import\OpeningAdvanceImportController;
use App\Http\Controllers\Import\OpeningMahasulImportController;
use App\Http\Controllers\Report\MemberLedgerController;
use App\Http\Controllers\Report\NeaLedgerController;
use App\Models\BankVoucher;
use App\Http\Controllers\Report\DashboardController;

/*
 * Authentication Routes
 * - Public routes for registration and login.
 * - Logout requires authentication via Sanctum.
 */

Route::post('/register', [UserController::class, 'register'])->name('register');
Route::post('/login', [UserController::class, 'login'])->name('login');
Route::get('/master-admin/companies/{user_id}/{software_type}', [UserController::class, 'getCompaniesBySoftwareType']);
Route::post('/master-admin/select-company/{user_id}/{software_type}/{company_id}', [UserController::class, 'masterAdminSelectCompany']);

Route::get('/location', [MemberEntryController::class, 'location'])->name('location');
Route::middleware('checkTokenExpiry')->post('/logout', [UserController::class, 'logout'])->name('logout');
Route::post('/refresh', [UserController::class, 'refresh'])->name('refresh');
Route::post('/forgot-password/send-otp', [ForgotPasswordController::class, 'sendOtp']);
Route::post('/forgot-password/verify-otp', [ForgotPasswordController::class, 'verifyOtp']);
Route::post('/forgot-password/reset', [ForgotPasswordController::class, 'resetPassword']);



Route::get('provinces', [ProvinceController::class, 'index']);
Route::get('/provinces/{id}/districts', [DistrictController::class, 'byProvince']);
Route::get('/districts/{district}/municipalities', [MunicipalityController::class, 'getByDistrict']);

Route::get('/locations', [MunicipalityController::class, 'getCascadingData']);
Route::middleware(['checkTokenExpiry', 'identify.tenant'])->group(function () {
    Route::get('/locations-for-tenant', [MunicipalityController::class, 'getCascadingDataforTenant']);
});

/*
 * File Management Routes
 */
Route::prefix('file')->middleware(['checkTokenExpiry'])->group(function () {
    Route::post('/upload', [FileUploadController::class, 'upload']);
    Route::get('/download/{filename}', [FileUploadController::class, 'download']);
    Route::get('/download-company/{filename}', [DownloadController::class, 'download']);
    
});

/*
 * Blacklist Management Routes
 */
Route::middleware(['checkTokenExpiry', 'identify.tenant'])->group(function () {
    Route::get('/blacklist-period', [BlacklistPeriodController::class, 'get'])->name('blacklist-period.get');
    Route::get('blacklist/list', [BlacklistPeriodController::class, 'getBlacklistedCustomers']);
    Route::get('blacklist/check-blacklist/{customer_id}/{reading_month}', [BlacklistPeriodController::class, 'checkBlacklist']);
    Route::put('/blacklist-period', [BlacklistPeriodController::class, 'update'])->name('blacklist-period.update');
    Route::patch('/blacklist/toggle', [BlacklistPeriodController::class, 'toggleApplyStatus']);
});



Route::prefix('disability-discount')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [DisabilityDiscountController::class, 'get']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->put('/', [DisabilityDiscountController::class, 'update']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])
        ->patch('/toggle', [DisabilityDiscountController::class, 'toggleApplyStatus']);

});


/*
 * Role Management Routes
 */

Route::prefix('roles')->group(function () {
    Route::middleware(['checkTokenExpiry', 'role:super admin'])->get('/', [RoleController::class, 'listRoles'])->name('roles.list');
    Route::middleware(['checkTokenExpiry', 'role:super admin'])->get('/by-company/{companyId}', [RoleController::class, 'listRolesByCompany'])->name('rolesbycompany.list');
    Route::middleware(['checkTokenExpiry', 'role:super admin'])->post('/create', [RoleController::class, 'createRole'])->name('roles.create');
    Route::middleware(['checkTokenExpiry', 'role:super admin'])->put('/edit', [RoleController::class, 'editRole'])->name('roles.edit');
    Route::middleware(['checkTokenExpiry', 'role:super admin'])->get('/detail/{roleId}', [RoleController::class, 'detail'])->name('roles.show');
    Route::middleware(['checkTokenExpiry', 'role:super admin'])->delete('/delete/{roleId}', [RoleController::class, 'deleteRole'])->name('roles.delete');
    Route::middleware(['checkTokenExpiry', 'role:super admin'])->post('/assign', [RoleController::class, 'assignRole'])->name('roles.assign');
    Route::middleware(['checkTokenExpiry', 'role:super admin'])->get('/list', [RoleController::class, 'listRolesonly'])->name('rolesonly.list');
    Route::middleware(['checkTokenExpiry', 'role:super admin'])->get('/roles-with-permission', [RoleController::class, 'Roleswithpermission'])->name('Roleswithpermission.list');
});

/*
 * role menu permission Routes
 */
Route::prefix('role-menu-permissions')->group(function () {
    Route::middleware(['checkTokenExpiry', 'role:super admin'])->get('/menus/{roleId}', [RoleMenuPermissionController::class, 'getRoleMenus'])->name('role-menu-permissions.get-menus');
    Route::middleware(['checkTokenExpiry', 'role:super admin'])->get('/menus-list', [RoleMenuPermissionController::class, 'getMenusList'])->name('role-menu-permissions.get-menus-list');
    Route::middleware(['checkTokenExpiry', 'role:super admin'])->post('/assign-menus/{roleId}', [RoleMenuPermissionController::class, 'assignMenusToRole'])->name('role-menu-permissions.assign-menus');
    Route::middleware(['checkTokenExpiry'])->get('/user-menus', [RoleMenuPermissionController::class, 'getUserMenus'])->name('role-menu-permissions.user-menus');
});

/*
 * Permission Management Routes
 */
Route::prefix('permissions')->group(function () {
    Route::middleware(['checkTokenExpiry', 'role:super admin'])->get('/', [PermissionController::class, 'listPermissions'])->name('permissions.list');
    Route::middleware(['checkTokenExpiry', 'role:super admin'])->post('/create', [PermissionController::class, 'createPermission'])->name('permissions.create');
    Route::middleware(['checkTokenExpiry', 'role:super admin'])->put('/edit', [PermissionController::class, 'editPermission'])->name('permissions.edit');
    Route::middleware(['checkTokenExpiry', 'role:super admin'])->delete('/delete', [PermissionController::class, 'deletePermission'])->name('permissions.delete');
    Route::middleware(['checkTokenExpiry', 'role:super admin'])->post('/assign-to-role', [PermissionController::class, 'assignPermissionToRole'])->name('permissions.assign-to-role');
    Route::middleware(['checkTokenExpiry', 'role:super admin'])->post('/assign-company-permissions', [PermissionController::class, 'assignPermissionsToCompanyOrganization'])->name('permissions.assign-to-company');
    Route::middleware(['checkTokenExpiry', 'role:super admin'])->get('/companies', [PermissionController::class, 'getAllCompaniesPermissions'])->name('permissions.companies.list');
    Route::middleware(['checkTokenExpiry', 'role:super admin'])->get('company/{companyId}', [PermissionController::class, 'getCompanyPermissions'])->name('permissions.company.list');
    Route::middleware(['checkTokenExpiry', 'role:super admin'])->post('/edit-to-role', [PermissionController::class, 'editPermissionToRole'])->name('permissions.edit-to-role');
    Route::middleware(['checkTokenExpiry', 'role:super admin'])->get('/listinjson', [PermissionController::class, 'PermissionsListinJson'])->name('PermissionsListinJson.list');
});

/*
 * User Management Routes
 */
Route::prefix('users')->group(function () {
    Route::middleware(['checkTokenExpiry', 'menu.permission'])->get('/', [UserController::class, 'listUsers'])->name('users.list');
    Route::middleware(['checkTokenExpiry', 'menu.permission'])->get('/{companyId}', [UserController::class, 'listCompanyUsers'])->name('userscompany.list');
    Route::middleware(['checkTokenExpiry', 'menu.permission'])->put('/edit/{id}', [UserController::class, 'editUser'])->name('users.edit');
    Route::middleware(['checkTokenExpiry', 'menu.permission'])->get('/detail/{id}', [UserController::class, 'detail'])->name('users.detail');
    Route::middleware(['checkTokenExpiry', 'menu.permission'])->delete('/delete/{id}', [UserController::class, 'deleteUser'])->name('users.delete');
    Route::middleware(['checkTokenExpiry', 'menu.permission'])->get('/permissions', [UserController::class, 'getUserPermissions'])->name('users.permissions');
    Route::middleware(['checkTokenExpiry', 'menu.permission'])->get('/list-with-permission', [UserController::class, 'UsersListwithPermission'])->name('UsersListwithPermission.list');
});

/*
 * Master Setup Routes
 */

Route::prefix('master-setups')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant', 'menu.permission'])->get('/type', [MasterSetupController::class, 'index'])->name('master-setups.index');
    Route::middleware(['checkTokenExpiry', 'identify.tenant', 'menu.permission'])->get('/', [MasterSetupController::class, 'listMasterSetups'])->name('master-setups.list');
    Route::middleware(['checkTokenExpiry', 'identify.tenant', 'menu.permission'])->get('/getById/{id}', [MasterSetupController::class, 'getById']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant', 'menu.permission'])->get('/order-numbers', [MasterSetupController::class, 'getOrderNumbers']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant', 'menu.permission'])->get('/getBank', [MasterSetupController::class, 'getBank']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant', 'menu.permission'])->post('/create', [MasterSetupController::class, 'createMasterSetup']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant', 'menu.permission'])->put('/{id}', [MasterSetupController::class, 'editMasterSetup']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant', 'menu.permission'])->patch('/{id}/toggle-active', [MasterSetupController::class, 'toggleActiveStatus']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant', 'menu.permission'])->delete('/{id}', [MasterSetupController::class, 'deleteMasterSetup']);

});





Route::prefix('wiring-persons')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [WiringPersonController::class, 'index']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/', [WiringPersonController::class, 'store']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/{wiringPerson}', [WiringPersonController::class, 'show']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])
        ->put('/{wiringPersonId}', [WiringPersonController::class, 'update']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant', 'permission:delete wiring persons'])->delete('/{wiringPerson}', [WiringPersonController::class, 'destroy']);
});




/*
 * Member Entry Routes
 */
Route::prefix('member-entries')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [MemberEntryController::class, 'listMemberEntries'])->name('member-entries.list');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/getById/{id}', [MemberEntryController::class, 'getById']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/listOccupationIds', [MemberEntryController::class, 'listOccupationIds'])->name('member-entries.listOccupationIds.list');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/listAreaIds', [MemberEntryController::class, 'listAreaIds'])->name('member-entries.listAreaIds.list');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/listBankIds', [MemberEntryController::class, 'listBankIds'])->name('member-entries.listBankIds.list');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/member-import', [MemberEntryController::class, 'importMemberEntry']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('import-field-names', [MemberEntryController::class, 'getImportFieldNames']);

    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/create', [MemberEntryController::class, 'createMemberEntry'])->name('member-entries.create');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/edit/{id}', [MemberEntryController::class, 'editMemberEntry'])->name('member-entries.edit');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->delete('/{id}', [MemberEntryController::class, 'deleteMemberEntry'])->name('member-entries.delete');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/generate-customer-id', [MemberEntryController::class, 'generateCustomerId'])->name('member-entries.generate-customer-id');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->patch('/{id}/toggle-active', [MemberEntryController::class, 'toggleActiveStatus'])->name('member-entries.toggle-active');
});

/*
 * Meter Issue Routes
 */
Route::prefix('meter-issues')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [MeterIssueController::class, 'listMeterIssues']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/customer/{id}', [MeterIssueController::class, 'listCustomeronly']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/phase-names', [MeterIssueController::class, 'listPhaseNames']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/capacity-names', [MeterIssueController::class, 'listCapacityNames']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/purpose-names', [MeterIssueController::class, 'listPurposeNames']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/transformer-names', [MeterIssueController::class, 'listTransformerNames']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/area-names', [MeterIssueController::class, 'listAreaNames']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/search-customers', [MeterIssueController::class, 'searchCustomers']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/getById/{id}', [MeterIssueController::class, 'getById']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/convertBsToAd/{date_bs}', [MeterIssueController::class, 'convertBsToAd']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/meter-import', [MeterIssueController::class, 'importExcel']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/create', [MeterIssueController::class, 'createMeterIssue']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->put('/{id}', [MeterIssueController::class, 'editMeterIssue']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->delete('/{id}', [MeterIssueController::class, 'deleteMeterIssue']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->patch('/{id}/toggle-active', [MeterIssueController::class, 'toggleActiveStatus']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/import-field-names', [MeterIssueController::class, 'getMeterIssueImportFieldNames']);
});


Route::post('/run-fines', [CronJobController::class, 'runFines']);


/*
 * Other Charges Routes
 */
Route::prefix('other-charges')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant', 'menu.permission'])->get('/', [OtherIncomeSetupController::class, 'listOtherCharges']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant', 'menu.permission'])->get('/all', [OtherIncomeSetupController::class, 'listOtherCharge']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant', 'menu.permission'])->post('/create', [OtherIncomeSetupController::class, 'store']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant', 'menu.permission'])->put('/{id}', [OtherIncomeSetupController::class, 'update']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant', 'menu.permission'])->delete('/{id}', [OtherIncomeSetupController::class, 'destroy']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant', 'menu.permission'])->patch('/{id}/toggle-active', [OtherIncomeSetupController::class, 'toggleActiveStatus']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant', 'menu.permission'])->get('/getById/{id}', [OtherIncomeSetupController::class, 'getById']);
});

/*
 * Discounts and Fines Routes
 */
Route::prefix('discounts-fines')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [DiscountAndFineController::class, 'listDiscountsAndFines'])->name('discounts-fines.list');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/create', [DiscountAndFineController::class, 'createDiscountAndFine'])->name('discounts-fines.create');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->put('/{id}', [DiscountAndFineController::class, 'editDiscountAndFine'])->name('discounts-fines.edit');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->delete('/{id}', [DiscountAndFineController::class, 'deleteDiscountAndFine'])->name('discounts-fines.delete');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/getById/{id}', [DiscountAndFineController::class, 'getById'])->name('discounts-fines.getById.list');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/discount-and-fine-exists', [DiscountAndFineController::class, 'hasNonDeletedRecord']);

});

Route::prefix('companies-profile')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [CompanyController::class, 'getCompany']);


});
Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/send-email', [EmailController::class, 'sendEmail']);


/*
 * Rebate Discount route
 */
Route::prefix('rebate-discounts')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [RebateDiscountController::class, 'list'])->name('rebate-discounts.list');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/create', [RebateDiscountController::class, 'create'])->name('rebate-discounts.create');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->put('/{id}', [RebateDiscountController::class, 'edit'])->name('rebate-discounts.edit');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->delete('/{id}', [RebateDiscountController::class, 'delete'])->name('rebate-discounts.delete');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/getById/{id}', [RebateDiscountController::class, 'getById'])->name('rebate-discounts.getById.list');
});

/*
 * Rates and Capacities Routes
 */
Route::prefix('rates-capacities')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/getById/{id}', [RateAndCapacityController::class, 'getById'])->name('rates-capacities.getById.list');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [RateAndCapacityController::class, 'listRatesAndCapacities'])->name('rates-capacities.list');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/create', [RateAndCapacityController::class, 'createRateAndCapacity'])->name('rates-capacities.create');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->put('/{id}', [RateAndCapacityController::class, 'editRateAndCapacity'])->name('rates-capacities.edit');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->delete('/{id}', [RateAndCapacityController::class, 'deleteRateAndCapacity'])->name('rates-capacities.delete');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/phase-names', [RateAndCapacityController::class, 'listPhaseNames'])->name('rates-capacities.phase-names.list');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/capacity-names', [RateAndCapacityController::class, 'listCapacityNames'])->name('rates-capacities.capacity-names.list');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/purpose-names', [RateAndCapacityController::class, 'listPurposeNames'])->name('rates-capacities.purpose-names.list');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/listRules', [RateAndCapacityController::class, 'listRules']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/charges', [RateAndCapacityController::class, 'listChargesForCombination']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/rates-by-phase-capacity-purpose', [RateAndCapacityController::class, 'getRatesByPhaseCapacityPurpose']);
});

/*
 * Tariff Setup Routes
 */
Route::prefix('tariff-setups')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/getById/{id}', [TariffSetupController::class, 'getById'])->name('tariff-setups.getById.list');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [TariffSetupController::class, 'listTariffSetups'])->name('tariff-setups.list');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/create', [TariffSetupController::class, 'createTariffSetup'])->name('tariff-setups.create');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->put('/{id}', [TariffSetupController::class, 'editTariffSetup'])->name('tariff-setups.edit');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->delete('/{id}', [TariffSetupController::class, 'deleteTariffSetup'])->name('tariff-setups.delete');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->patch('/{id}/toggle-active', [TariffSetupController::class, 'toggleActiveStatus'])->name('tariff-setups.toggle-active');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/active', [TariffSetupController::class, 'getActiveTariffSetup'])->name('tariff-setups.active');
});

/*
 * Meter Reading Entry Routes
 */
Route::prefix('meter-reading-entries')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [MeterReadingEntryController::class, 'listMeterReadingEntries'])->name('meter-reading-entries.list');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/getById/{id}', [MeterReadingEntryController::class, 'getById'])->name('meter-reading-entries.getById.list');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/create', [MeterReadingEntryController::class, 'createMeterReadingEntry'])->name('meter-reading-entries.create');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->put('/{id}', [MeterReadingEntryController::class, 'editMeterReadingEntry'])->name('meter-reading-entries.edit');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->delete('/delete/{id}', [MeterReadingEntryController::class, 'deleteMeterReadingEntry'])->name('meter-reading-entries.delete');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/rates-capacities', [MeterReadingEntryController::class, 'listRatesAndCapacities'])->name('meter-reading-entries.rates-capacities.list');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/customers/search', [MeterReadingEntryController::class, 'searchCustomerDetails']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/members/{memberId}', [MeterReadingEntryController::class, 'getMember']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/customers/load', [MeterReadingEntryController::class, 'loadAllCustomerDetails']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/customers/area/{area_id}', [MeterReadingEntryController::class, 'loadCustomersByArea']);



});

/*
 * Other Income Receipts Routes
 */
Route::prefix('other-income-receipts')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [OtherIncomeReceiptController::class, 'listOtherIncomeSetups']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/receipts', [OtherIncomeReceiptController::class, 'listOtherIncomeReceipts']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/create', [OtherIncomeReceiptController::class, 'store']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->put('/{id}', [OtherIncomeReceiptController::class, 'update']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->delete('/{id}', [OtherIncomeReceiptController::class, 'deleteOtherIncomeReceipt']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/customers/search', [OtherIncomeReceiptController::class, 'searchCustomerDetails']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/getById/{id}', [OtherIncomeReceiptController::class, 'getById']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/cancel-receipts/{id}', [OtherIncomeReceiptController::class, 'cancelReceipt']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/customer-details/{customer_id}', [OtherIncomeReceiptController::class, 'listCustomerDetails']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/income-heads-content/{customer_id}', [OtherIncomeReceiptController::class, 'listOtherIncomeReceiptContent']);
});

/*
 * Name Transfer Entry Routes
 */
Route::prefix('name-transfer-entries')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/transfer', [NameTransferController::class, 'transferMeterIssue']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [NameTransferController::class, 'listtransferMeterIssue'])->name('meter-issues.transfers.list');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/transfers/{id}', [NameTransferController::class, 'showTransferEntry'])->name('meter-issues.transfers.show');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->delete('/transfers/{id}', [NameTransferController::class, 'deleteTransferEntry'])->name('meter-issues.transfers.delete');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/transfers/{id}/restore', [NameTransferController::class, 'restoreTransferEntry'])->name('meter-issues.transfers.restore');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/restore', [NameTransferController::class, 'restoreMeterIssue'])->name('meter-issues.restore');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/customers/search', [NameTransferController::class, 'searchCustomerDetails'])->name('meter-issues.searchCustomerDetails');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/members/no-meter-issue', [NameTransferController::class, 'membersWithoutMeterIssue'])->name('meter-issues.membersWithoutMeterIssue');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/getById/{id}', [NameTransferController::class, 'getById'])->name('meter-issues.transfers.getById.list');
});

/*
 * Date Conversion Routes
 */
Route::middleware(['checkTokenExpiry'])->group(function () {
    Route::get('/ad-to-bs', [DateConversionController::class, 'adToBs']);
    Route::get('/bs-to-ad', [DateConversionController::class, 'bsToAd']);
    Route::get('/current-bs-date', [DateConversionController::class, 'getCurrentBsDate']);
});

/*
 * Opening Meter Deposit Entry Routes
 */
Route::prefix('opening-meter-deposit-entries')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [OpeningMeterDepositEntryController::class, 'listOpeningMeterDepositEntries']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/create', [OpeningMeterDepositEntryController::class, 'createOpeningMeterDepositEntry']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->put('/{id}', [OpeningMeterDepositEntryController::class, 'editOpeningMeterDepositEntry']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->delete('/{id}', [OpeningMeterDepositEntryController::class, 'deleteOpeningMeterDepositEntry']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/ad-to-bs', [OpeningMeterDepositEntryController::class, 'adToBs']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/customer-details/{customer_id}', [OpeningMeterDepositEntryController::class, 'listCustomerDetails']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/getById/{id}', [OpeningMeterDepositEntryController::class, 'getById']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/customers/search', [OpeningMeterDepositEntryController::class, 'searchCustomerDetails']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/import', [OpeningMeterDepositEntryController::class, 'importExcel']);
});

/*
 * Share Opening Entry Routes
 */
Route::prefix('share-opening-entries')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [ShareOpeningEntryController::class, 'listShareOpeningEntries']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/create', [ShareOpeningEntryController::class, 'createShareOpeningEntry']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->put('/{id}', [ShareOpeningEntryController::class, 'editShareOpeningEntry']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->delete('/{id}', [ShareOpeningEntryController::class, 'deleteShareOpeningEntry']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/ad-to-bs', [ShareOpeningEntryController::class, 'adToBs']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/customer-details/{customer_id}', [ShareOpeningEntryController::class, 'listCustomerDetails']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/getById/{id}', [ShareOpeningEntryController::class, 'getById']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/customers/search', [ShareOpeningEntryController::class, 'searchCustomerDetails']);
        Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/import', [ShareOpeningEntryController::class, 'importExcel']);

});

/*
 * Opening Mahasul Balance Entry Routes
 */
Route::prefix('opening-mahasul-balance-entries')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [OpeningMahasulBalanceEntryController::class, 'listOpeningMahasulBalanceEntries'])->name('opening-mahasul-balance-entries.list');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/create', [OpeningMahasulBalanceEntryController::class, 'createOpeningMahasulBalanceEntry'])->name('opening-mahasul-balance-entries.create');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->put('/{id}', [OpeningMahasulBalanceEntryController::class, 'editOpeningMahasulBalanceEntry'])->name('opening-mahasul-balance-entries.edit');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->delete('/{id}', [OpeningMahasulBalanceEntryController::class, 'deleteOpeningMahasulBalanceEntry'])->name('opening-mahasul-balance-entries.delete');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/getById/{id}', [OpeningMahasulBalanceEntryController::class, 'getById'])->name('opening-mahasul-balance-entries.getById');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/customers/search', [OpeningMahasulBalanceEntryController::class, 'searchCustomerDetails'])->name('opening-mahasul-balance-entries.searchCustomerDetails');
});

/*
 * Change Meter Customer Routes
 */
Route::prefix('change-meter-customer')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [ChangeMeterController::class, 'list'])->name('change-meter-customer.list');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/{id}', [ChangeMeterController::class, 'getById'])->name('change-meter-customer.getById');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/create', [ChangeMeterController::class, 'changeMeter'])->name('change-meter-customer.create');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->put('/{id}', [ChangeMeterController::class, 'edit'])->name('change-meter-customer.edit');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->delete('/{id}', [ChangeMeterController::class, 'delete'])->name('change-meter-customer.delete');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/customers/search', [ChangeMeterController::class, 'searchCustomerDetails'])->name('change-meter-customer.searchCustomerDetails');
});

/*
 * Share Entry Routes
 */
Route::prefix('share-entry')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/list', [ShareTransactionController::class, 'indexShareEntries']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/create', [ShareTransactionController::class, 'createShareEntry']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/share-import', [ShareTransactionController::class, 'importShareExcel']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/getById/{id}', [ShareTransactionController::class, 'showShareEntry']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/cancel/{id}', [ShareTransactionController::class, 'cancelShareEntry']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/customers/search', [ShareTransactionController::class, 'searchShareEntries']);

});



Route::prefix('share-return')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [ShareTransactionController::class, 'indexShareReturns']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/create', [ShareTransactionController::class, 'createShareReturn']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/getById/{id}', [ShareTransactionController::class, 'showShareReturn']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->patch('/cancel/{id}', [ShareTransactionController::class, 'cancelShareReturn']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/customers/search', [ShareTransactionController::class, 'searchShareReturns']);
});

/*
 * Deposit Entry Routes
 */


Route::prefix('deposit-entry')->group(function () {

    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/create', [MeterDepositTransactionController::class, 'createDeposit']);
    Route::middleware([
        'checkTokenExpiry',
        'identify.tenant'
    ])->get('/customers/search', [MeterDepositTransactionController::class, 'searchCustomerDetails']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [MeterDepositTransactionController::class, 'indexDeposits'])
        ->middleware(['checkTokenExpiry', 'identify.tenant']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/getById/{id}', [MeterDepositTransactionController::class, 'showDeposit']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->patch('/cancel/{id}', [MeterDepositTransactionController::class, 'cancelDeposit']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/customers/search', [MeterDepositTransactionController::class, 'searchDeposits']);


});


/*
 * Upgrade Meter Capacity Routes
 */
Route::prefix('upgrade-meter-capacity')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/list', [UpgradeMeterCapacityController::class, 'listUpgradeMeterCapacities'])->name('upgrade-meter-capacity.list');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/list/{id}', [UpgradeMeterCapacityController::class, 'getUpgradeMeterCapacityById'])->name('upgrade-meter-capacity.getById');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/create', [MeterDepositTransactionController::class, 'createUpgrade'])->name('upgrade-meter-capacity.create');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->put('/{id}', [UpgradeMeterCapacityController::class, 'editUpgradeMeterCapacity'])->name('upgrade-meter-capacity.edit');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->delete('/{id}', [UpgradeMeterCapacityController::class, 'deleteUpgradeMeterCapacity'])->name('upgrade-meter-capacity.delete');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->patch('/cancel-upgrade/{id}', [UpgradeMeterCapacityController::class, 'cancelUpgrade']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/customers/search', [MeterDepositTransactionController::class, 'searchUpgrade']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->patch('/{id}/toggle-active', [UpgradeMeterCapacityController::class, 'toggleActiveStatus']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/getById/{id}', [MeterDepositTransactionController::class, 'showMeterUpgrade']);
});

Route::prefix('deposit-return')->group(function () {

    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/create', [MeterDepositTransactionController::class, 'createReturn']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [MeterDepositTransactionController::class, 'indexReturns'])
        ->middleware(['checkTokenExpiry', 'identify.tenant']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/customers/search', [MeterDepositTransactionController::class, 'searchReturns']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/deposit-detail/{id}', [MeterDepositTransactionController::class, 'getMeterDepositDetail']);

    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/getById/{id}', [MeterDepositTransactionController::class, 'showReturn']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->patch('/return-cancel/{id}', [MeterDepositTransactionController::class, 'cancelReturn']);


});


/*
 * Mahsul Receipt Routes
 */
Route::prefix('mahsul-receipt')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [MahasulReceiptEntryController::class, 'index']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/create', [MahasulReceiptEntryController::class, 'store']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/getById/{id}', [MahasulReceiptEntryController::class, 'customerDetail']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/customers/search', [MahasulReceiptEntryController::class, 'searchCustomer']);
});

/*
 * Meter Insurance Routes
 */
Route::prefix('meter-insurance')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [MeterInsuranceController::class, 'index']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/create', [MeterInsuranceController::class, 'store']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/getById/{id}', [MeterInsuranceController::class, 'getById'])->name('meter-insurance.getById');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/customers/search', [MeterInsuranceController::class, 'searchCustomerDetails']);
});

/*
 * Advance Payment Routes
 */
Route::prefix('advance-payment')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [AdvancePaymentController::class, 'list'])->name('advance-payment.list');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/create', [AdvancePaymentController::class, 'create'])->name('advance-payment.create');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/getById/{id}', [AdvancePaymentController::class, 'getById'])->name('advance-payment.getById');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/customers/search', [AdvancePaymentController::class, 'searchCustomerDetails'])->name('advance-payment.searchCustomerDetails');
});

/*
 * Non-Member Payment Routes
 */
Route::prefix('non-member-payment')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [NonMemberPaymentController::class, 'list'])->name('non-member-payment.list');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/create', [NonMemberPaymentController::class, 'create'])->name('non-member-payment.create');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/getById/{id}', [NonMemberPaymentController::class, 'getById'])->name('non-member-payment.getById');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/customers/search', [NonMemberPaymentController::class, 'searchCustomerDetails'])->name('non-member-payment.searchCustomerDetails');
});

/*
 * NEA Purchase Routes
 */
Route::prefix('nea-purchase')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [NEAPurchaseController::class, 'list']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/create', [NEAPurchaseController::class, 'create']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->put('/{id}', [NEAPurchaseController::class, 'edit']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->delete('/{id}', [NEAPurchaseController::class, 'delete']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/getById/{id}', [NEAPurchaseController::class, 'getById']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/listTransformers', [NEAPurchaseController::class, 'listTransformers']);
});

/*
 * NEA Payment Routes
 */
Route::prefix('nea-payment')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [NEAPaymentEntryController::class, 'index']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/create', [NEAPaymentEntryController::class, 'store']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->put('/{id}', [NEAPaymentEntryController::class, 'update']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->delete('/{id}', [NEAPaymentEntryController::class, 'destroy']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/getById/{id}', [NEAPaymentEntryController::class, 'getById']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/listTransformers', [NEAPurchaseController::class, 'listTransformers']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/payments/due-amount', [NEAPaymentEntryController::class, 'getDueAmount']);
});



// Route::prefix('expense-trackers')->group(function () {

//    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/expenses', [ExpenseAndReceivableTrackerController::class, 'indexExpense']);
//     Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/expenses/create', [ExpenseAndReceivableTrackerController::class, 'storeExpense']);

//     Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/receivables', [ExpenseAndReceivableTrackerController::class, 'indexReceivable']);
//     Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/receivables/create', [ExpenseAndReceivableTrackerController::class, 'storeReceivable']);

//     Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/getById/{id}', [ExpenseAndReceivableTrackerController::class, 'show']);
//     Route::middleware(['checkTokenExpiry', 'identify.tenant'])->put('/{id}', [ExpenseAndReceivableTrackerController::class, 'update']);
//     Route::middleware(['checkTokenExpiry', 'identify.tenant'])->delete('/{id}', [ExpenseAndReceivableTrackerController::class, 'destroy']);
// });

Route::prefix('trackers')->group(function () {

    // =====================
    // EXPENSES (type = 0)
    // =====================
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/expenses', [ExpenseAndReceivableTrackerController::class, 'indexExpenses']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/expenses', [ExpenseAndReceivableTrackerController::class, 'storeExpense']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/getById/{id}', [ExpenseAndReceivableTrackerController::class, 'show']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->put('/expenses/{id}', [ExpenseAndReceivableTrackerController::class, 'updateExpense']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->delete('/{id}', [ExpenseAndReceivableTrackerController::class, 'destroy']);

    // =====================
    // RECEIVABLES (type = 1)
    // =====================
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/receivables', [ExpenseAndReceivableTrackerController::class, 'indexReceivables']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/receivables', [ExpenseAndReceivableTrackerController::class, 'storeReceivable']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->put('/receivables/{id}', [ExpenseAndReceivableTrackerController::class, 'updateReceivable']);
});

Route::prefix('journal-vouchers')->group(function () {

    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [JournalVoucherController::class, 'index']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/create', [JournalVoucherController::class, 'store']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/getById/{id}', [JournalVoucherController::class, 'show']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->put('/{id}', [JournalVoucherController::class, 'update']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->delete('/{id}', [JournalVoucherController::class, 'destroy']);
});

Route::prefix('bank-vouchers')->group(function () {

    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [BankVoucherController::class, 'index']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/create', [BankVoucherController::class, 'store']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/getById/{id}', [BankVoucherController::class, 'show']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->put('/{id}', [BankVoucherController::class, 'update']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->delete('/{id}', [BankVoucherController::class, 'destroy']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/account-balance/{account_head_id}', [BankVoucherController::class, 'getAccountBalance']);
});

Route::prefix('opening-balance-entry')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [OpeningBalanceController::class, 'index']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/create', [OpeningBalanceController::class, 'store']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/current', [OpeningBalanceController::class, 'show']);
        Route::middleware(['checkTokenExpiry', 'identify.tenant'])->put('/change', [OpeningBalanceController::class, 'change']); 
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->delete('/delete', [OpeningBalanceController::class, 'destroy']);
});


/*
 * Measure Units Routes
 */
Route::prefix('measure-units')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [MeasureUnitController::class, 'list']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/create', [MeasureUnitController::class, 'create']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->put('/{id}', [MeasureUnitController::class, 'edit']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->delete('/{id}', [MeasureUnitController::class, 'destroy']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->patch('/{id}/toggle-active', [MeasureUnitController::class, 'toggleActiveStatus']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/getById/{id}', [MeasureUnitController::class, 'getById']);
});

/*
 * Main Groups Routes
 */
Route::prefix('main-groups')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [MainGroupController::class, 'index']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/create', [MainGroupController::class, 'store']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->put('/{id}', [MainGroupController::class, 'update']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->delete('/{id}', [MainGroupController::class, 'destroy']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->patch('/{id}/toggle-active', [MainGroupController::class, 'toggleActive']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/getById/{id}', [MainGroupController::class, 'show']);
});

/*
 * Sub Groups Routes
 */
Route::prefix('sub-groups')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [SubGroupController::class, 'index']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/sub-group-list', [SubGroupController::class, 'getSubGroup']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/maingroupList', [SubGroupController::class, 'maingroupList']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/create', [SubGroupController::class, 'store']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->put('/{id}', [SubGroupController::class, 'update']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->delete('/{id}', [SubGroupController::class, 'destroy']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->patch('/{id}/toggle-active', [SubGroupController::class, 'toggleActive']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/getById/{id}', [SubGroupController::class, 'getById']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/main-group/{mainGroupId}', [SubGroupController::class, 'getSubGroupsByMainGroup']);

});

/*
 * Account Groups Routes
 */
Route::prefix('account-groups')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [AccountGroupController::class, 'index']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/getAccountGroup', [AccountGroupController::class, 'getAccountGroup']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/create', [AccountGroupController::class, 'store']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->put('/{id}', [AccountGroupController::class, 'update']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->delete('/{id}', [AccountGroupController::class, 'destroy']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/getById/{id}', [AccountGroupController::class, 'getById']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->patch('/{id}/toggle-active', [AccountGroupController::class, 'toggleActive']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/sub-group/{id}', [AccountGroupController::class, 'getBySubGroup']);


});

/*
 * Account Heads Routes
 */
Route::prefix('account-heads')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant', 'menu.permission'])->get('/', [AccountHeadController::class, 'index']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant', 'menu.permission'])->get('/getAccountHead', [AccountHeadController::class, 'getAccountHead']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant', 'menu.permission'])->post('/create', [AccountHeadController::class, 'store']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant', 'menu.permission'])->put('/{id}', [AccountHeadController::class, 'update']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant', 'menu.permission'])->delete('/{id}', [AccountHeadController::class, 'destroy']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant', 'menu.permission'])->get('/getById/{id}', [AccountHeadController::class, 'getById']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant', 'menu.permission'])->get('/account-group/{id}', [AccountHeadController::class, 'getByAccountGroup']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant', 'menu.permission'])->patch('/{id}/toggle-active', [AccountHeadController::class, 'toggleActive']);
});

Route::prefix('fines')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant', 'permission:view fines'])->get('/', [FineController::class, 'index']);

});


Route::prefix('customer-transaction')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/{id}', [CustomerTrasnsactionController::class, 'ListAllTransactionbyChargeType']);

});

Route::prefix('import')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/opening-mahasul/upload', [OpeningMahasulImportController::class, 'upload']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/opening-advance/upload', [OpeningAdvanceImportController::class, 'upload']);
    

});
Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/opening-advance/import-field-names', [OpeningAdvanceImportController::class, 'getOpeningAdvanceImportFieldNames']);
Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/opening-mahasul/import-field-names', [OpeningMahasulImportController::class, 'getOpeningMahasulImportFieldNames']);
Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/opening-meter-deposit/import-field-names', [OpeningMeterDepositEntryController::class, 'getOpeningMeterDepositEntryImportFieldNames']);
Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/opening-share-entry/import-field-names', [ShareOpeningEntryController::class, 'getOpeningShareEntryImportFieldNames']);

Route::prefix('member-report')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [MemberReportController::class, 'index']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/page', [MemberReportController::class, 'indexwithpagination']);


});

Route::prefix('meter-issue-report')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [MeterIssueReportController::class, 'index']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/page', [MeterIssueReportController::class, 'indexwithpagination']);
});

Route::prefix('meter-issue-transfer-report')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [NameTransferReportController::class, 'index']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/page', [NameTransferReportController::class, 'indexwithpagination']);
});

Route::prefix('meter-deposit-report')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [MeterDepositReportController::class, 'index']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/page', [MeterDepositReportController::class, 'indexwithpagination']);
});

Route::prefix('opening-meter-deposit-report')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [OpeningMeterDepositReportController::class, 'index']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/page', [OpeningMeterDepositReportController::class, 'indexwithpagination']);
});

Route::prefix('deposit-report')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [DepositReportController::class, 'index']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/page', [DepositReportController::class, 'indexwithpagination']);
});

Route::prefix('nea-report')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [NeaReportController::class, 'index']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/page', [NeaReportController::class, 'indexwithpagination']);
});

Route::prefix('nea-leakage-report')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [NeaLeakageReportController::class, 'index']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/page', [NeaLeakageReportController::class, 'indexwithpagination']);
});

Route::prefix('absent-ledger')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [AbsentLedgerController::class, 'index']);

});

Route::prefix('present-ledger')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [PresentLedgerController::class, 'index']);

});

Route::prefix('ledger')->group(function () {
    //Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [CustomerLedgerController::class, 'index']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/customer-ledger', [MemberLedgerController::class, 'showLedger']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/nea-ledger', [NeaLedgerController::class, 'index']);

});

Route::prefix('opening-mahasul-balance-report')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [OpeningMahasulBalanceReportController::class, 'index']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/page', [OpeningMahasulBalanceReportController::class, 'indexWithPagination']);
});

Route::prefix('meter-reading-report')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [MeterReadingReportController::class, 'index']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/page', [MeterReadingReportController::class, 'indexwithpagination']);
});

Route::prefix('meter-readings-full-report')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [MeterReadingReportController::class, 'readingReport']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/all', [MeterReadingReportController::class, 'readingReportAll']);
});

Route::prefix('mahasul-receipts-reports')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/list', [MahasulReceiptReportController::class, 'index']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/mahsul-only', [MahasulReceiptReportController::class, 'mahasulIndex']);

});

Route::prefix('fiscal-years')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/list', [FiscalYearController::class, 'index']);

});

Route::prefix('customer-due-reports')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/list', [CustomerDueReportController::class, 'index']);
});

Route::prefix('advance-report')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [AdvanceReportController::class, 'index']);
});

Route::prefix('change-meter-report')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [ChangeMeterReportController::class, 'index']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/page', [ChangeMeterReportController::class, 'indexWithPagination']);
});

Route::prefix('nea-purchase-report')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [NeaPurchaseReportController::class, 'index']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/page', [NeaPurchaseReportController::class, 'indexwithpagination']);
});

Route::prefix('income_head-report')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [IncomeHeadReportController::class, 'index']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/get', [IncomeHeadReportController::class, 'get']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/getOtherIncomeReceiptReport', [IncomeHeadReportController::class, 'getOtherIncomeReceiptReport']);
});

Route::prefix('voucher-summary')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [VoucherSummaryController::class, 'index']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/ledger', [VoucherSummaryController::class, 'ledgerList']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/getAllVoucherNumbers', [VoucherSummaryController::class, 'getAllVoucherNumbers']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/voucher-list', [VoucherSummaryController::class, 'getVoucherList']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/{id}/full-detail', [VoucherSummaryController::class, 'getPreviewFromVoucher']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/list-with-account-head', [VoucherSummaryController::class, 'geListWithAccountHead']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/list-of-account-head-with-member-and-account-group-filter', [VoucherSummaryController::class, 'getListWithMemberAndAccountFilter']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->put('/cancel-voucher/{id}', [VoucherSummaryController::class, 'voucherCancel']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/{id}', [VoucherSummaryController::class, 'show']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/voucher-types', [VoucherSummaryController::class, 'getVoucherTypes']);

});

Route::prefix('companies')->group(function () {
    Route::middleware(['checkTokenExpiry', 'role:super admin'])->get('/', [CompanyController::class, 'listCompanies']);
    Route::middleware(['checkTokenExpiry', 'role:super admin'])->post('/create', [CompanyController::class, 'createCompany']);
    Route::middleware(['checkTokenExpiry', 'role:super admin'])->post('/{companyId}/users', [CompanyController::class, 'createCompanyUser']);
    Route::middleware(['checkTokenExpiry', 'role:super admin'])->post('/enter-company', [CompanyController::class, 'enterCompany']);
    Route::middleware(['checkTokenExpiry', 'role:super admin'])->post('/exit-company', [CompanyController::class, 'exitCompany']);
    Route::middleware(['checkTokenExpiry', 'role:super admin'])->put('/{companyId}', [CompanyController::class, 'updateCompany']);
    Route::middleware(['checkTokenExpiry', 'role:super admin'])->get('/{companyId}', [CompanyController::class, 'getCompanyById']);
    Route::middleware(['checkTokenExpiry', 'role:super admin'])->delete('/{companyId}', [CompanyController::class, 'deleteCompany']);
});

Route::prefix('member-information')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/member-details', [MemberDetailsController::class, 'show']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/meter-deposit', [MemberDetailsController::class, 'meterDepositDetails']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/share-details', [ShareDetailsController::class, 'show']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/deposit-details', [DepositDetailsController::class, 'show']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant',])->get('/other-income-details', [OtherIncomeDetailsController::class, 'show']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant',])->get('/meter-reading-details', [MeterReadingDetailsController::class, 'show']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/mahsul-details', [MahsulDetailsController::class, 'show']);
});

Route::middleware(['checkTokenExpiry', 'identify.tenant'])->group(function () {
    Route::get('/generateOtherIncomeReceipt', [GenerateCodeController::class, 'generateOtherIncomeReceipt']);
    Route::get('/generateShareEntry', [GenerateCodeController::class, 'generateShareEntry']);
    Route::get('/generateDepositEntry', [GenerateCodeController::class, 'generateDepositEntry']);
    Route::get('/generateShareReturnEntry', [GenerateCodeController::class, 'generateShareReturnEntry']);
    Route::get('/generateDepositReturnEntry', [GenerateCodeController::class, 'generateDepositReturnEntry']);
    Route::get('/generateMahasulReceipt', [GenerateCodeController::class, 'generateMahasulReceipt']);
    Route::get('/generateMeterInsurance', [GenerateCodeController::class, 'generateMeterInsurance']);
    Route::get('/generateAdvancePayment', [GenerateCodeController::class, 'generateAdvancePayment']);
    Route::get('/generateNeaPayment', [GenerateCodeController::class, 'generateNeaPayment']);
    Route::get('/generateNonMemberPayment', [GenerateCodeController::class, 'generateNonMemberPayment']);
    Route::get('/generateUpgradeMeterCapacity', [GenerateCodeController::class, 'generateUpgradeMeterCapacity']);
    Route::get('/generateExpenseAndReceivableTrackerVoucher/{type}', [GenerateCodeController::class, 'generatExpenseAndReceivableTrackerVoucher']);
    Route::get('/generateJournalVoucher', [GenerateCodeController::class, 'generateJournalVoucher']);
    Route::get('/generateBankVoucher', [GenerateCodeController::class, 'generateBankVoucher']);
    Route::get('/generateJournalVoucher', [GenerateCodeController::class, 'generateJournalVoucher']);
});



Route::prefix('sync')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/upload', [SyncController::class, 'upload'])->name('sync.upload');
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->post('/download', [SyncController::class, 'download'])->name('sync.download');
});


Route::prefix('opening-mahasul-fine-setups')->group(function () {
     Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/get-status', [OpeningMahasulFineSetupController::class, 'status']);
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->patch('/toggle', [OpeningMahasulFineSetupController::class, 'toggle']);
});
Route::prefix('dashboard')->group(function () {
    Route::middleware(['checkTokenExpiry', 'identify.tenant'])->get('/', [DashboardController::class, 'dashboardSummary']);
});