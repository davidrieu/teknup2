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
	const selectedFileRef = useRef(null);

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

		// Scroll to selected file section
		setTimeout(() => {
			selectedFileRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}, 100);
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
			{/* Header with quota info */}
			<div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '24px' }}>
				<h2 className="teknup-heading" style={{ margin: 0, color: '#fff' }}>Upload Your Track</h2>
				{quota && !quota.unlimited && (
					<div style={{ color: '#fff', fontSize: '14px', fontWeight: '500' }}>
						Used {quota.usage}/{quota.limit}
					</div>
				)}
			</div>

			{quota && quota.remaining === 0 && !quota.unlimited && (
				<div className="teknup-card" style={{ marginBottom: '24px', background: 'rgba(255, 152, 0, 0.1)', borderColor: 'rgba(255, 152, 0, 0.3)' }}>
					<div style={{ textAlign: 'center' }}>
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
						<div className="teknup-upload-icon">
							<svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path d="M12 4L12 16M12 4L8 8M12 4L16 8" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
								<path d="M4 17V19C4 19.5304 4.21071 20.0391 4.58579 20.4142C4.96086 20.7893 5.46957 21 6 21H18C18.5304 21 19.0391 20.7893 19.4142 20.4142C19.7893 20.0391 20 19.5304 20 19V17" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
							</svg>
						</div>
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
						<div ref={selectedFileRef} className="teknup-card" style={{ marginTop: '24px' }}>
							<h3>Selected File</h3>
							<p className="teknup-text">{file.name}</p>
							<p className="teknup-text-secondary">{(file.size / 1024 / 1024).toFixed(2)} MB</p>

							<div style={{ marginTop: '24px' }}>
								<label className="teknup-label">Mastering Intensity</label>
								<div className="teknup-intensity-buttons">
									{[
										{ value: 'light', label: 'Light' },
										{ value: 'medium', label: 'Medium' },
										{ value: 'heavy', label: 'Heavy' }
									].map((intensity) => (
										<button
											key={intensity.value}
											type="button"
											className={`teknup-intensity-button ${settings.intensity === intensity.value ? 'active' : ''}`}
											onClick={() => setSettings({ ...settings, intensity: intensity.value })}
										>
											{intensity.label}
										</button>
									))}
								</div>
							</div>

							<div style={{ marginTop: '24px' }}>
								<label className="teknup-label">Music Style</label>
								<div className="teknup-genre-buttons">
									{[
										{ value: 'techno', label: 'Techno' },
										{ value: 'minimal_techno', label: 'Minimal' },
										{ value: 'hard_techno', label: 'Hard' },
										{ value: 'industrial_techno', label: 'Industrial' },
										{ value: 'melodic_techno', label: 'Melodic' },
										{ value: 'acid_techno', label: 'Acid' },
										{ value: 'house', label: 'House' },
										{ value: 'trance', label: 'Trance' },
										{ value: 'electro', label: 'Electro' }
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
										Free Trial Complete
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
