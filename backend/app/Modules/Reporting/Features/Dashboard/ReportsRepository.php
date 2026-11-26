<?php
namespace App\Modules\Reporting\Features\Dashboard;

/**
 * Facade delegating each detailed report to its focused query class.
 */
final class ReportsRepository
{
    public static function enforceScope(array $filters, array $actor): ?int
    {
        return ReportSqlHelper::enforceScope($filters, $actor);
    }

    public static function meta(array $actor): array
    {
        return ReportSqlHelper::meta($actor);
    }

    public static function attendance(array $filters, array $actor): array
    {
        return AttendanceReportQuery::run($filters, $actor);
    }

    public static function revenue(array $filters, array $actor): array
    {
        return RevenueReportQuery::run($filters, $actor);
    }

    public static function classPerformance(array $filters, array $actor): array
    {
        return ClassPerformanceReportQuery::run($filters, $actor);
    }

    public static function payroll(array $filters, array $actor): array
    {
        return PayrollReportQuery::run($filters, $actor);
    }

    public static function debt(array $filters, array $actor): array
    {
        return DebtReportQuery::run($filters, $actor);
    }

    public static function admissions(array $filters, array $actor): array
    {
        return AdmissionsReportQuery::run($filters, $actor);
    }
}
