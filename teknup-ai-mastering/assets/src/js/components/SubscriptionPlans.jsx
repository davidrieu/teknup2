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
			const response = await fetch(teknupData.restUrl + 'subscription-plans');

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

	const handlePurchase = (productId) => {
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
						<div className="teknup-plan-badge">Best Value</div>
					)}

					<div className="teknup-plan-header">
						<h3>{plan.name}</h3>
						<div className="teknup-plan-price">
							<span className="price-currency">{plan.currency}</span>
							<span className="price-amount">{plan.price}</span>
							<span className="price-period">/month</span>
						</div>
					</div>

					<div className="teknup-plan-features">
						<ul>
							{plan.features && plan.features.length > 0 ? (
								plan.features.map((feature, index) => (
									<li key={index}>
										<span className="feature-icon">✓</span>
										{feature}
									</li>
								))
							) : (
								<>
									<li>
										<span className="feature-icon">🎵</span>
										{plan.monthly_limit} masters per month
									</li>
									<li>
										<span className="feature-icon">⚡</span>
										Professional AI mastering
									</li>
									<li>
										<span className="feature-icon">🎚️</span>
										All audio formats supported
									</li>
									<li>
										<span className="feature-icon">📊</span>
										High-quality export
									</li>
								</>
							)}
						</ul>
					</div>

					<button
						className="teknup-button teknup-button-primary"
						onClick={() => handlePurchase(plan.id)}
					>
						Subscribe Now
					</button>
				</div>
			))}
		</div>
	);
};

export default SubscriptionPlans;
