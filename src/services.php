<?php
declare(strict_types=1);

use App\Application;
use App\Container;
use App\Controller\AdminController;
use App\Controller\GroupController;
use App\Controller\LocationController;
use App\Controller\OvertimeController;
use App\Controller\ProjectController;
use App\Controller\UserController;
use App\Database;
use App\Repository\ActivityLogRepository;
use App\Repository\AdminMemberRepository;
use App\Repository\EmployeeRepository;
use App\Repository\GroupApproverRepository;
use App\Repository\GroupRepository;
use App\Repository\HolidayRepository;
use App\Repository\LeaveRepository;
use App\Repository\LocationRepository;
use App\Repository\OvertimeRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Service\ActivityLogger;
use App\Service\AdminAccessService;
use App\Service\ApprovalFinalizer;
use App\Service\ApproverDirectoryService;
use App\Service\EmailTemplate;
use App\Service\MailService;
use App\Service\Mailer;
use App\Service\OvertimeApprovalService;
use App\Service\OvertimeSubmissionService;

/**
 * Wiring for the whole app. Factories run on first get(), so a request only
 * opens the database connections it actually touches.
 */
return function (Container $c, array $config): void {
    $c->set('config', static fn (): array => $config);
    $c->set('config.approval_cutoff_time', static fn (): string => (string) ($config['app']['approval_cutoff_time'] ?? '15:00'));
    $c->set('config.admin_group_abbrs', static fn (): array => $config['app']['admin_group_abbrs'] ?? []);

    $c->set(Database::class, static fn (): Database => new Database($config['connections'] ?? $config));

    foreach (['webjmr', 'kdtph', 'kdtphnew', 'forms'] as $connection) {
        $c->set('db.' . $connection, static fn (Container $c) => $c->get(Database::class)->getConnection($connection));
    }

    $c->set(ActivityLogRepository::class, static fn (Container $c) => new ActivityLogRepository($c->get('db.webjmr')));
    $c->set(AdminMemberRepository::class, static fn (Container $c) => new AdminMemberRepository($c->get('db.webjmr')));
    $c->set(EmployeeRepository::class, static fn (Container $c) => new EmployeeRepository($c->get('db.kdtphnew')));
    $c->set(GroupApproverRepository::class, static fn (Container $c) => new GroupApproverRepository($c->get('db.webjmr')));
    $c->set(GroupRepository::class, static fn (Container $c) => new GroupRepository($c->get('db.kdtphnew')));
    $c->set(HolidayRepository::class, static fn (Container $c) => new HolidayRepository($c->get('db.kdtph')));
    $c->set(LeaveRepository::class, static fn (Container $c) => new LeaveRepository($c->get('db.forms')));
    $c->set(LocationRepository::class, static fn (Container $c) => new LocationRepository($c->get('db.webjmr')));
    $c->set(OvertimeRepository::class, static fn (Container $c) => new OvertimeRepository($c->get('db.webjmr')));
    $c->set(ProjectRepository::class, static fn (Container $c) => new ProjectRepository($c->get('db.webjmr')));
    $c->set(UserRepository::class, static fn (Container $c) => new UserRepository($c->get('db.kdtph')));

    $c->set(ActivityLogger::class, static fn (Container $c) => new ActivityLogger(
        $c->get(ActivityLogRepository::class)
    ));

    $c->set(AdminAccessService::class, static fn (Container $c) => new AdminAccessService(
        $c->get(AdminMemberRepository::class),
        $c->get(EmployeeRepository::class),
        $c->get('config.admin_group_abbrs')
    ));

    $c->set(ApproverDirectoryService::class, static fn (Container $c) => new ApproverDirectoryService(
        $c->get(GroupApproverRepository::class),
        $c->get(UserRepository::class),
        $c->get(EmployeeRepository::class)
    ));

    $c->set(OvertimeSubmissionService::class, static fn (Container $c) => new OvertimeSubmissionService(
        $c->get(OvertimeRepository::class),
        $c->get(EmployeeRepository::class),
        $c->get(HolidayRepository::class),
        $c->get(LeaveRepository::class),
        $c->get(ApproverDirectoryService::class),
        $c->get(ActivityLogger::class),
        $c->get('config.approval_cutoff_time')
    ));

    $c->set(ApprovalFinalizer::class, static fn (Container $c) => new ApprovalFinalizer(
        $c->get(OvertimeRepository::class),
        $c->get(ActivityLogger::class),
        $c->get('config.approval_cutoff_time')
    ));

    $c->set(OvertimeApprovalService::class, static fn (Container $c) => new OvertimeApprovalService(
        $c->get(OvertimeRepository::class),
        $c->get(ApprovalFinalizer::class),
        $c->get(ActivityLogger::class)
    ));

    $c->set(Mailer::class, static fn (): Mailer => new Mailer($config['mail'] ?? []));

    $c->set(EmailTemplate::class, static fn (): EmailTemplate => new EmailTemplate());

    $c->set(MailService::class, static fn (Container $c) => new MailService(
        $c->get(Mailer::class),
        $c->get(EmailTemplate::class),
        $config['mail'] ?? [],
        (string) ($config['app']['url'] ?? ''),
        $c->get(EmployeeRepository::class)
    ));

    $c->set(UserController::class, static fn (Container $c) => new UserController(
        $c->get(UserRepository::class),
        $c->get(GroupApproverRepository::class),
        $c->get(AdminAccessService::class),
        $c->get('config.approval_cutoff_time')
    ));

    $c->set(AdminController::class, static fn (Container $c) => new AdminController(
        $c->get(ActivityLogRepository::class),
        $c->get(UserRepository::class),
        $c->get(EmployeeRepository::class),
        $c->get(GroupApproverRepository::class),
        $c->get(AdminMemberRepository::class),
        $c->get(AdminAccessService::class),
        $c->get(ActivityLogger::class)
    ));

    $c->set(GroupController::class, static fn (Container $c) => new GroupController(
        $c->get(GroupRepository::class),
        $c->get(UserRepository::class)
    ));

    $c->set(LocationController::class, static fn (Container $c) => new LocationController(
        $c->get(LocationRepository::class),
        $c->get(UserRepository::class)
    ));

    $c->set(ProjectController::class, static fn (Container $c) => new ProjectController(
        $c->get(ProjectRepository::class),
        $c->get(UserRepository::class),
        $c->get(EmployeeRepository::class),
        $c->get(ApproverDirectoryService::class)
    ));

    $c->set(OvertimeController::class, static fn (Container $c) => new OvertimeController(
        $c->get(UserRepository::class),
        $c->get(HolidayRepository::class),
        $c->get(LeaveRepository::class),
        $c->get(EmployeeRepository::class),
        $c->get(ApproverDirectoryService::class),
        $c->get(OvertimeSubmissionService::class),
        $c->get(OvertimeApprovalService::class)
    ));

    $c->set(Application::class, static fn (Container $c) => new Application($config, $c));
};
