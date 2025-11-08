import React from 'react';

const ComingSoon = ({ title, description }) => {
	return (
		<div className="teknup-coming-soon">
			<div className="teknup-coming-soon-content">
				<span className="teknup-coming-soon-icon">🚧</span>
				<h2 className="teknup-heading" style={{ color: '#fff' }}>{title}</h2>
				<div className="teknup-beta-badge">BETA</div>
				<p className="teknup-coming-soon-description">
					{description || 'This feature is currently in development and will be available to the public soon.'}
				</p>
				<div className="teknup-coming-soon-features">
					<h3>Upcoming Features:</h3>
					<ul>
						<li>✨ Intuitive and easy-to-use interface</li>
						<li>🎯 Optimized processing for techno music</li>
						<li>⚡ Fast and high-quality results</li>
						<li>🔒 Secure and confidential</li>
					</ul>
				</div>
				<p className="teknup-coming-soon-notify">
					<strong>Stay tuned!</strong> We'll notify you as soon as this feature is available.
				</p>
			</div>
		</div>
	);
};

export default ComingSoon;
