import React from 'react';
import { createRoot } from 'react-dom/client';
import MasteringApp from './components/MasteringApp';

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', () => {
	const container = document.getElementById('teknup-mastering-app');

	if (container) {
		const root = createRoot(container);
		root.render(<MasteringApp />);
	}
});
