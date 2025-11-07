import React, { useState, useRef } from 'react';
import SubscriptionPlans from './SubscriptionPlans';

const Upload = () => {
	const [file, setFile] = useState(null);
	const [uploading, setUploading] = useState(false);
	const [progress, setProgress] = useState(0);
	const [job, setJob] = useState(null);
	const [error, setError] = useState(null);
	const [quota, setQuota] = useState(null);
	const [quotaExhausted, setQuotaExhausted] = useState(false);
	const [showSubscriptionPopup, setShowSubscriptionPopup] = useState(false);
	const [settings, setSettings] = useState({
		intensity: 'high',
		genre: 'techno',
		target_lufs: -9
	});

	const fileInputRef = useRef(null);

	// Fetch quota on mount
	React.useEffect(() => {
		fetchQuota();
	}, []);

	const fetchQuota = async () => {
		try {
			const response = await fetch(window.teknupData.restUrl + 'quota', {
				headers: {
					'X-WP-Nonce': window.teknupData.nonce
				}
			});
			const data = await response.json();
			setQuota(data);

			// Check if quota is exhausted (don't reload yet, let user download first)
			if (data.plan === 'none' || (data.remaining === 0 && !data.unlimited && data.plan === 'free_trial')) {
				setQuotaExhausted(true);
			}
		} catch (err) {
			console.error('Failed to fetch quota:', err);
		}
	};

	const handleDrop = (e) => {
		e.preventDefault();
		e.stopPropagation();

		const droppedFile = e.dataTransfer.files[0];
		validateAndSetFile(droppedFile);
	};

	const handleFileInput = (e) => {
		const selectedFile = e.target.files[0];
		validateAndSetFile(selectedFile);
	};

	const validateAndSetFile = (file) => {
		if (!file) return;

		// Check file type
		const allowedTypes = window.teknupData.allowedTypes;
		const allowedExtensions = window.teknupData.allowedExtensions;
		const fileExt = file.name.split('.').pop().toLowerCase();

		if (!allowedTypes.includes(file.type) && !allowedExtensions.includes(fileExt)) {
			setError('Invalid file type. Please upload WAV, MP3, FLAC, or AIFF files only.');
			return;
		}

		// Check file size
		if (file.size > window.teknupData.maxFileSize) {
			setError(`File too large. Maximum size is ${window.teknupData.maxFileSize / 1024 / 1024} MB.`);
			return;
		}

		setFile(file);
		setError(null);
	};

	const handleUpload = async () => {
		if (!file) return;

		// Check if quota is exceeded
		if (quota && quota.remaining === 0 && !quota.unlimited) {
			setError('You have reached your monthly limit. Please upgrade your plan to continue.');
			return;
		}

		setUploading(true);
		setProgress(0);
		setError(null);

		const formData = new FormData();
		formData.append('file', file);
		formData.append('intensity', settings.intensity);
		if (settings.genre) formData.append('genre', settings.genre);
		if (settings.target_lufs) formData.append('target_lufs', settings.target_lufs);

		try {
			const response = await fetch(window.teknupData.restUrl + 'upload', {
				method: 'POST',
				headers: {
					'X-WP-Nonce': window.teknupData.nonce
				},
				body: formData
			});

			const data = await response.json();

			if (data.error) {
				setError(data.error);
				setUploading(false);
				return;
			}

			if (data.job) {
				setJob(data.job);
				pollJobStatus(data.job.id);
			}
		} catch (err) {
			setError('Upload failed. Please try again.');
			setUploading(false);
		}
	};

	const pollJobStatus = async (jobId) => {
		const interval = setInterval(async () => {
			try {
				const response = await fetch(window.teknupData.restUrl + `jobs/${jobId}`, {
					headers: {
						'X-WP-Nonce': window.teknupData.nonce
					}
				});

				const data = await response.json();

				if (data) {
					setJob(data);

					if (data.status === 'completed' || data.status === 'failed') {
						clearInterval(interval);
						setUploading(false);
						fetchQuota(); // Refresh quota
					}
				}
			} catch (err) {
				console.error('Failed to poll job status:', err);
			}
		}, 2000); // Poll every 2 seconds
	};

	const resetUpload = () => {
		setFile(null);
		setJob(null);
		setProgress(0);
		setError(null);
		setUploading(false);
	};

	const handleUploadAnother = () => {
		// If quota is exhausted, reload page to show subscription page
		if (quotaExhausted) {
			window.location.reload();
		} else {
			resetUpload();
		}
	};

	const handleDownload = (e) => {
		// If quota is exhausted, show subscription popup after download
		if (quotaExhausted) {
			// Let the download happen first, then show popup
			setTimeout(() => {
				setShowSubscriptionPopup(true);
			}, 1000); // Wait 1 second to ensure download started
		}
	};

	return (
		<div className="teknup-upload-container">
			<h2 className="teknup-heading">Upload Your Track</h2>

			{quota && (
				<div className="teknup-quota-info teknup-card" style={{ marginBottom: '24px' }}>
					<h3>Your Plan: {quota.plan_name}</h3>
					<div style={{ marginTop: '8px' }}>
						{quota.unlimited ? (
							<p>Unlimited masters per month</p>
						) : (
							<>
								<p>Used: {quota.usage} / {quota.limit} masters this month</p>
								<div className="teknup-progress-bar">
									<div className="teknup-progress-fill" style={{ width: `${quota.percentage}%` }}></div>
								</div>
							</>
						)}
					</div>
				</div>
			)}

			{quota && quota.remaining === 0 && !quota.unlimited && (
				<div className="teknup-card" style={{ marginBottom: '24px', background: 'rgba(255, 152, 0, 0.1)', borderColor: 'rgba(255, 152, 0, 0.3)' }}>
					<div style={{ textAlign: 'center' }}>
						<div style={{ fontSize: '48px', marginBottom: '16px' }}>🔒</div>
						<h3>Monthly Limit Reached</h3>
						<p style={{ marginTop: '8px', marginBottom: '16px' }}>
							You've used all {quota.limit} master{quota.limit > 1 ? 's' : ''} for this month.
						</p>
						<a
							href={window.teknupData.siteUrl + '/shop'}
							className="teknup-button"
							style={{ display: 'inline-block' }}
						>
							Upgrade Your Plan
						</a>
					</div>
				</div>
			)}

			{!job && (
				<>
					<div
						className="teknup-upload-zone"
						onDrop={handleDrop}
						onDragOver={(e) => e.preventDefault()}
						onClick={() => fileInputRef.current?.click()}
					>
						<div className="teknup-upload-icon">🎵</div>
						<h3>Drop your audio file here</h3>
						<p className="teknup-text-secondary">or click to browse</p>
						<p className="teknup-text-secondary" style={{ marginTop: '16px' }}>
							Supports: WAV, MP3, FLAC, AIFF (max 500 MB)
						</p>
						<input
							ref={fileInputRef}
							type="file"
							accept=".wav,.mp3,.flac,.aiff,.aif"
							onChange={handleFileInput}
							style={{ display: 'none' }}
						/>
					</div>

					{file && (
						<div className="teknup-card" style={{ marginTop: '24px' }}>
							<h3>Selected File</h3>
							<p className="teknup-text">{file.name}</p>
							<p className="teknup-text-secondary">{(file.size / 1024 / 1024).toFixed(2)} MB</p>

							<div style={{ marginTop: '24px' }}>
								<label className="teknup-label">Mastering Intensity</label>
								<div className="teknup-intensity-slider-container">
									<input
										type="range"
										className="teknup-intensity-slider"
										min="0"
										max="2"
										step="1"
										value={settings.intensity === 'light' ? 0 : settings.intensity === 'medium' ? 1 : 2}
										onChange={(e) => {
											const values = ['light', 'medium', 'heavy'];
											setSettings({ ...settings, intensity: values[parseInt(e.target.value)] });
										}}
									/>
									<div className="teknup-intensity-labels">
										<span className={settings.intensity === 'light' ? 'active' : ''}>Light</span>
										<span className={settings.intensity === 'medium' ? 'active' : ''}>Medium</span>
										<span className={settings.intensity === 'heavy' ? 'active' : ''}>Heavy</span>
									</div>
								</div>
							</div>

							<div style={{ marginTop: '24px' }}>
								<label className="teknup-label">Techno Style</label>
								<div className="teknup-genre-buttons">
									{[
										{ value: 'techno', label: 'Techno' },
										{ value: 'minimal_techno', label: 'Minimal' },
										{ value: 'hard_techno', label: 'Hard' },
										{ value: 'industrial_techno', label: 'Industrial' },
										{ value: 'melodic_techno', label: 'Melodic' },
										{ value: 'acid_techno', label: 'Acid' }
									].map((genre) => (
										<button
											key={genre.value}
											type="button"
											className={`teknup-genre-button ${settings.genre === genre.value ? 'active' : ''}`}
											onClick={() => setSettings({ ...settings, genre: genre.value })}
										>
											{genre.label}
										</button>
									))}
								</div>
							</div>

							<div style={{ marginTop: '16px' }}>
								<label className="teknup-label">Target LUFS</label>
								<input
									type="number"
									className="teknup-input"
									value={settings.target_lufs}
									onChange={(e) => setSettings({ ...settings, target_lufs: parseFloat(e.target.value) })}
									min="-30"
									max="0"
									step="0.1"
								/>
								<p className="teknup-text-secondary" style={{ marginTop: '8px' }}>
									Club standard: -9 LUFS (recommended for techno), Streaming: -14 LUFS
								</p>
							</div>

							<div style={{ marginTop: '24px', display: 'flex', gap: '16px' }}>
								<button
									className="teknup-button"
									onClick={handleUpload}
									disabled={uploading || (quota && quota.remaining === 0 && !quota.unlimited)}
								>
									{uploading ? 'Processing...' : (quota && quota.remaining === 0 && !quota.unlimited) ? 'Quota Exceeded' : 'Start Mastering'}
								</button>
								<button
									className="teknup-button teknup-button-secondary"
									onClick={resetUpload}
									disabled={uploading}
								>
									Cancel
								</button>
							</div>
						</div>
					)}
				</>
			)}

			{job && (
				<div className="teknup-card" style={{ marginTop: '24px' }}>
					<h3>Job Status</h3>
					<p className="teknup-text">{job.original_filename}</p>
					<div style={{ marginTop: '16px' }}>
						<span className={`teknup-status-badge teknup-status-${job.status}`}>
							{job.status.replace('_', ' ').toUpperCase()}
						</span>
					</div>

					{uploading && (
						<div style={{ marginTop: '16px' }}>
							<div className="teknup-spinner"></div>
							<p className="teknup-text-secondary" style={{ textAlign: 'center', marginTop: '8px' }}>
								Processing your track...
							</p>
						</div>
					)}

					{job.status === 'completed' && job.download_url && (
						<>
							{quotaExhausted && (
								<div style={{
									marginTop: '16px',
									padding: '16px',
									background: 'rgba(255, 152, 0, 0.1)',
									borderRadius: '8px',
									border: '1px solid rgba(255, 152, 0, 0.3)'
								}}>
									<p style={{ margin: 0, color: '#FF9800', fontWeight: 'bold' }}>
										⚠️ Free Trial Complete
									</p>
									<p style={{ margin: '8px 0 0 0', fontSize: '14px' }}>
										Download your mastered track below. To master more tracks, you'll need to subscribe to a plan.
									</p>
								</div>
							)}
							<div style={{ marginTop: '24px' }}>
								<a
									href={job.download_url}
									className="teknup-button"
									download
									onClick={handleDownload}
								>
									Download Mastered Track
								</a>
								{!quotaExhausted && (
									<button
										className="teknup-button teknup-button-secondary"
										onClick={resetUpload}
										style={{ marginLeft: '16px' }}
									>
										Upload Another
									</button>
								)}
							</div>
						</>
					)}

					{job.status === 'failed' && (
						<div style={{ marginTop: '24px' }}>
							<p style={{ color: '#F44336' }}>{job.error_message}</p>
							<button className="teknup-button" onClick={resetUpload}>
								Try Again
							</button>
						</div>
					)}
				</div>
			)}

			{error && (
				<div className="teknup-card" style={{ marginTop: '24px', borderColor: '#F44336' }}>
					<p style={{ color: '#F44336' }}>{error}</p>
				</div>
			)}

			{/* Subscription Popup Modal */}
			{showSubscriptionPopup && (
				<div style={{
					position: 'fixed',
					top: 0,
					left: 0,
					right: 0,
					bottom: 0,
					backgroundColor: 'rgba(0, 0, 0, 0.8)',
					display: 'flex',
					alignItems: 'center',
					justifyContent: 'center',
					zIndex: 9999,
					padding: '20px'
				}}>
					<div style={{
						background: 'linear-gradient(135deg, #1a1a2e 0%, #16213e 100%)',
						borderRadius: '16px',
						padding: '32px',
						maxWidth: '900px',
						width: '100%',
						maxHeight: '90vh',
						overflowY: 'auto',
						position: 'relative',
						border: '1px solid rgba(255, 255, 255, 0.1)',
						boxShadow: '0 20px 60px rgba(0, 0, 0, 0.5)'
					}}>
						{/* Close button */}
						<button
							onClick={() => setShowSubscriptionPopup(false)}
							style={{
								position: 'absolute',
								top: '16px',
								right: '16px',
								background: 'rgba(255, 255, 255, 0.1)',
								border: 'none',
								color: '#fff',
								fontSize: '24px',
								width: '40px',
								height: '40px',
								borderRadius: '50%',
								cursor: 'pointer',
								display: 'flex',
								alignItems: 'center',
								justifyContent: 'center',
								transition: 'all 0.3s ease'
							}}
							onMouseOver={(e) => e.target.style.background = 'rgba(255, 255, 255, 0.2)'}
							onMouseOut={(e) => e.target.style.background = 'rgba(255, 255, 255, 0.1)'}
						>
							×
						</button>

						{/* Popup header */}
						<div style={{ textAlign: 'center', marginBottom: '32px' }}>
							<div style={{ fontSize: '48px', marginBottom: '16px' }}>🎉</div>
							<h2 style={{ fontSize: '28px', marginBottom: '8px', color: '#fff' }}>
								Your Track is Ready!
							</h2>
							<p style={{ fontSize: '16px', color: 'rgba(255, 255, 255, 0.7)' }}>
								To continue mastering more tracks, choose a subscription plan below
							</p>
						</div>

						{/* Subscription plans */}
						<SubscriptionPlans />

						{/* Footer */}
						<div style={{ textAlign: 'center', marginTop: '24px', paddingTop: '24px', borderTop: '1px solid rgba(255, 255, 255, 0.1)' }}>
							<button
								onClick={() => setShowSubscriptionPopup(false)}
								className="teknup-button teknup-button-secondary"
							>
								Maybe Later
							</button>
						</div>
					</div>
				</div>
			)}
		</div>
	);
};

export default Upload;
