import React, { useState } from 'react';
import Upload from './Upload';
import Dashboard from './Dashboard';

const MasteringApp = () => {
	const [activeTab, setActiveTab] = useState('upload');

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
