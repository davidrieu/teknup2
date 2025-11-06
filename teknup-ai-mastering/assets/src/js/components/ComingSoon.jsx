import React from 'react';

const ComingSoon = ({ title, description }) => {
	return (
		<div className="teknup-coming-soon">
			<div className="teknup-coming-soon-content">
				<span className="teknup-coming-soon-icon">🚧</span>
				<h2 className="teknup-heading">{title}</h2>
				<div className="teknup-beta-badge">BETA</div>
				<p className="teknup-coming-soon-description">
					{description || 'Cette fonctionnalité est actuellement en développement et sera bientôt disponible au public.'}
				</p>
				<div className="teknup-coming-soon-features">
					<h3>Fonctionnalités à venir :</h3>
					<ul>
						<li>✨ Interface intuitive et facile à utiliser</li>
						<li>🎯 Traitement optimisé pour la musique techno</li>
						<li>⚡ Résultats rapides et de haute qualité</li>
						<li>🔒 Sécurisé et confidentiel</li>
					</ul>
				</div>
				<p className="teknup-coming-soon-notify">
					<strong>Restez connecté !</strong> Nous vous informerons dès que cette fonctionnalité sera disponible.
				</p>
			</div>
		</div>
	);
};

export default ComingSoon;
