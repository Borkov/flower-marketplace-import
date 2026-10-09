import { Routes } from '@angular/router';
import { OrdersPageComponent } from './pages/orders-page/orders-page.component';

export const routes: Routes = [
  { path: '', component: OrdersPageComponent },
  { path: '**', redirectTo: '' },
];
