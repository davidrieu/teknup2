import React from 'react';
import SubscriptionPlans from './SubscriptionPlans';

const NoSubscription = () => {
	return (
		<div className="teknup-no-subscription">
			<div className="teknup-no-subscription-header">
				<div className="teknup-no-subscription-icon">🔒</div>
				<h2>Active Subscription Required</h2>
				<p>You need an active subscription to use Teknup AI Mastering</p>
			</div>

			<div className="teknup-no-subscription-content">
				<div className="teknup-no-subscription-info">
					<h3>Why Subscribe?</h3>
					<ul>
						<li>
							<span className="info-icon">🎵</span>
							<div>
								<strong>Professional AI Mastering</strong>
								<p>Powered by Dolby.io for studio-quality results</p>
							</div>
						</li>
						<li>
							<span className="info-icon">⚡</span>
							<div>
								<strong>Fast Processing</strong>
								<p>Get your mastered tracks in minutes</p>
							</div>
						</li>
						<li>
							<span className="info-icon">🎚️</span>
							<div>
								<strong>Advanced Controls</strong>
								<p>Customize intensity, genre, and LUFS settings</p>
							</div>
						</li>
						<li>
							<span className="info-icon">📊</span>
							<div>
								<strong>Dashboard & Analytics</strong>
								<p>Track your mastering history and statistics</p>
							</div>
						</li>
					</ul>
				</div>

				<div className="teknup-no-subscription-plans">
					<h3>Choose Your Plan</h3>
					<SubscriptionPlans />
				</div>
			</div>

			<div className="teknup-no-subscription-footer">
				<p>Already subscribed? Try refreshing the page or <a href="#" onClick={(e) => { e.preventDefault(); window.location.reload(); }}>click here</a></p>
			</div>
		</div>
	);
};

export default NoSubscription;
