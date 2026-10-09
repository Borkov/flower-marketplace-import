import { InjectionToken } from '@angular/core';

/** Base URL for API; empty string = same origin (ng proxy /api). */
export const API_BASE_URL = new InjectionToken<string>('API_BASE_URL', {
  providedIn: 'root',
  factory: () => '',
});
