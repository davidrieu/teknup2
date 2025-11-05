import React, { useState } from 'react';
import Upload from './Upload';
import Dashboard from './Dashboard';
import Auth from './Auth';

const MasteringApp = () => {
	const [activeTab, setActiveTab] = useState('upload');
	const isLoggedIn = teknupData.isLoggedIn || false;

	// If user is not logged in, show Auth component
	if (!isLoggedIn) {
		return <Auth onAuthSuccess={() => window.location.reload()} />;
	}

	// Show main app for logged in users
	return (
		<div className="teknup-mastering-app">
			{/* Navigation Tabs */}
			<div className="teknup-tabs">
				<button
					className={`teknup-tab ${activeTab === 'upload' ? 'active' : ''}`}
					onClick={() => setActiveTab('upload')}
				>
					<span className="teknup-tab-icon">🎵</span>
					Upload Track
				</button>
				<button
					className={`teknup-tab ${activeTab === 'dashboard' ? 'active' : ''}`}
					onClick={() => setActiveTab('dashboard')}
				>
					<span className="teknup-tab-icon">📊</span>
					Dashboard
				</button>
			</div>

			{/* Tab Content */}
			<div className="teknup-tab-content">
				{activeTab === 'upload' && <Upload />}
				{activeTab === 'dashboard' && <Dashboard />}
			</div>
		</div>
	);
};

export default MasteringApp;
