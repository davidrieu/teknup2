import React, { useState } from 'react';
import Upload from './Upload';
import Dashboard from './Dashboard';
import Auth from './Auth';
import NoSubscription from './NoSubscription';

const MasteringApp = () => {
	const [activeTab, setActiveTab] = useState('upload');
	const isLoggedIn = teknupData.isLoggedIn || false;
	const hasActiveSubscription = teknupData.hasActiveSubscription || false;

	// If user is not logged in, show Auth component
	if (!isLoggedIn) {
		return <Auth onAuthSuccess={() => window.location.reload()} />;
	}

	// If user is logged in but doesn't have an active subscription, show subscription required
	if (!hasActiveSubscription) {
		return <NoSubscription />;
	}

	// Show main app for logged in users with active subscription
	return (
		<div className="teknup-mastering-app">
			{/* Navigation Tabs */}
			<div className="teknup-tabs">
				<button
					className={`teknup-tab ${activeTab === 'upload' ? 'active' : ''}`}
					onClick={() => setActiveTab('upload')}
				>
					<span className="teknup-tab-icon">🎵</span>
					Mastering
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
