import React from 'react';
import { createRoot } from 'react-dom/client';
import Dashboard from './components/Dashboard';

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', () => {
	const container = document.getElementById('teknup-dashboard-app');

	if (container) {
		const root = createRoot(container);
		root.render(<Dashboard />);
	}
});
