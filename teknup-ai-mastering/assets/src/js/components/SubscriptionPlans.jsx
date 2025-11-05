import React, { useState, useEffect } from 'react';

const SubscriptionPlans = () => {
	const [plans, setPlans] = useState([]);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState('');

	useEffect(() => {
		fetchPlans();
	}, []);

	const fetchPlans = async () => {
		try {
			const response = await fetch(teknupData.restUrl + 'subscription-plans', {
				headers: {
					'X-WP-Nonce': teknupData.nonce,
				},
			});

			const data = await response.json();

			if (!response.ok) {
				throw new Error(data.message || 'Failed to load plans');
			}

			setPlans(data.plans || []);
		} catch (err) {
			setError(err.message);
		} finally {
			setLoading(false);
		}
	};

	const handleSubscribe = (productId) => {
		// Add to cart and redirect to checkout
		const checkoutUrl = `${teknupData.siteUrl}/checkout/?add-to-cart=${productId}`;
		window.location.href = checkoutUrl;
	};

	if (loading) {
		return (
			<div className="teknup-plans-loading">
				<div className="teknup-spinner"></div>
				<p>Loading subscription plans...</p>
			</div>
		);
	}

	if (error) {
		return (
			<div className="teknup-message teknup-message-error">
				{error}
			</div>
		);
	}

	if (plans.length === 0) {
		return (
			<div className="teknup-message teknup-message-warning">
				No subscription plans available at the moment.
			</div>
		);
	}

	return (
		<div className="teknup-subscription-plans">
			{plans.map((plan) => (
				<div
					key={plan.id}
					className={`teknup-plan-card ${plan.featured ? 'featured' : ''}`}
				>
					{plan.featured && (
						<div className="teknup-plan-badge">Most Popular</div>
					)}

					<div className="teknup-plan-header">
						<h3>{plan.name}</h3>
						<div className="teknup-plan-price">
							{plan.price === 0 || plan.price === '0' ? (
								<>
									<span className="price-amount">Free</span>
								</>
							) : (
								<>
									<span className="price-currency">{plan.currency}</span>
									<span className="price-amount">{plan.price}</span>
									<span className="price-period">/{plan.period}</span>
								</>
							)}
						</div>
					</div>

					<div className="teknup-plan-features">
						<ul>
							<li>
								<span className="feature-icon">🎵</span>
								<strong>{plan.limit === 'unlimited' ? 'Unlimited' : plan.limit}</strong>{' '}
								{plan.limit === 'unlimited' ? 'tracks' : 'tracks per month'}
							</li>
							<li>
								<span className="feature-icon">⚡</span>
								AI-powered mastering with Dolby.io
							</li>
							<li>
								<span className="feature-icon">🎚️</span>
								Advanced settings (intensity, genre, LUFS)
							</li>
							<li>
								<span className="feature-icon">📊</span>
								Dashboard & statistics
							</li>
							{plan.limit === 'unlimited' && (
								<>
									<li>
										<span className="feature-icon">⏱️</span>
										Priority processing
									</li>
									<li>
										<span className="feature-icon">💾</span>
										Extended file storage
									</li>
								</>
							)}
						</ul>
					</div>

					<button
						className="teknup-button teknup-button-primary"
						onClick={() => handleSubscribe(plan.id)}
					>
						{plan.price === 0 || plan.price === '0' ? 'Start Free Trial' : 'Subscribe Now'}
					</button>
				</div>
			))}
		</div>
	);
};

export default SubscriptionPlans;
