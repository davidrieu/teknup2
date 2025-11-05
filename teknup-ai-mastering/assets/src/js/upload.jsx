import React from 'react';
import { createRoot } from 'react-dom/client';
import Upload from './components/Upload';

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', () => {
	const container = document.getElementById('teknup-upload-app');

	if (container) {
		const root = createRoot(container);
		root.render(<Upload />);
	}
});
