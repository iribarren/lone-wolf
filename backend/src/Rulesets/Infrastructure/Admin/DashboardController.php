<?php

declare(strict_types=1);

namespace App\Rulesets\Infrastructure\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin_dashboard')]
final class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()->setTitle('Lone Wolf — Backoffice');
    }

    public function configureAssets(): Assets
    {
        return parent::configureAssets()
            ->addJsFile('assets/admin-flow-editor.js');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToRoute('Game systems', 'fa fa-dice-d20', 'admin_dashboard_system_index');
        yield MenuItem::linkToRoute('Campaign flows', 'fa fa-diagram-project', 'admin_dashboard_game_flow_index');
        yield MenuItem::linkToRoute('Oracles', 'fa fa-book-skull', 'admin_dashboard_oracle_index');
    }
}
