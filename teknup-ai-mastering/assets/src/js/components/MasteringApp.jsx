import React, { useState } from 'react';
import Upload from './Upload';
import Dashboard from './Dashboard';
import Auth from './Auth';
import NoSubscription from './NoSubscription';
import ComingSoon from './ComingSoon';

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
					Mastering
				</button>
				<button
					className={`teknup-tab ${activeTab === 'mix' ? 'active' : ''}`}
					onClick={() => setActiveTab('mix')}
				>
					<span className="teknup-tab-icon">🎚️</span>
					Mix
					<span className="teknup-tab-badge">BETA</span>
				</button>
				<button
					className={`teknup-tab ${activeTab === 'stems' ? 'active' : ''}`}
					onClick={() => setActiveTab('stems')}
				>
					Stem Separation
					<span className="teknup-tab-badge">BETA</span>
				</button>
				<button
					className={`teknup-tab ${activeTab === 'dashboard' ? 'active' : ''}`}
					onClick={() => setActiveTab('dashboard')}
				>
					Dashboard
				</button>
			</div>

			{/* Tab Content */}
			<div className="teknup-tab-content">
				{activeTab === 'upload' && <Upload />}
				{activeTab === 'mix' && (
					<ComingSoon
						title="Mix Multitrack"
						description="Mix your multitrack projects with AI. This advanced feature will allow you to automatically mix multiple audio tracks to create a professional mix optimized for techno and electronic music."
					/>
				)}
				{activeTab === 'stems' && (
					<ComingSoon
						title="Stem Separation"
						description="Separate your music into individual stems (vocals, drums, bass, other). Extract and download each element of your mix for total control over your production."
					/>
				)}
				{activeTab === 'dashboard' && <Dashboard />}
			</div>
		</div>
	);
};

export default MasteringApp;
