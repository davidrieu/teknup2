import React, { useState, useRef } from 'react';

const StemSeparation = () => {
	const [file, setFile] = useState(null);
	const [uploading, setUploading] = useState(false);
	const [progress, setProgress] = useState(0);
	const [job, setJob] = useState(null);
	const [error, setError] = useState(null);
	const [quota, setQuota] = useState(null);
	const [settings, setSettings] = useState({
		intensity: 'high',
		genre: 'techno',
		target_lufs: -9,
		getProcessedStems: true
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
		} catch (err) {
			console.error('Failed to fetch quota:', err);
		}
	};

	const handleFileChange = (e) => {
		const selectedFile = e.target.files[0];
		if (selectedFile) {
			setFile(selectedFile);
			setError(null);
		}
	};

	const handleDrop = (e) => {
		e.preventDefault();
		const droppedFile = e.dataTransfer.files[0];
		if (droppedFile) {
			setFile(droppedFile);
			setError(null);
		}
	};

	const handleDragOver = (e) => {
		e.preventDefault();
	};

	const handleUpload = async () => {
		if (!file) {
			setError('Please select a file first');
			return;
		}

		// Check quota
		if (quota && !quota.unlimited && quota.remaining <= 0) {
			setError('You have reached your monthly quota. Please upgrade your plan.');
			return;
		}

		setUploading(true);
		setError(null);
		setProgress(0);

		const formData = new FormData();
		formData.append('file', file);
		formData.append('job_type', 'stem_separation');
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

			// Poll for job status
			setJob(data);
			pollJobStatus(data.id);

		} catch (err) {
			setError('Upload failed. Please try again.');
			setUploading(false);
		}
	};

	const pollJobStatus = async (jobId) => {
		const maxAttempts = 120; // 10 minutes max
		let attempts = 0;

		const poll = async () => {
			if (attempts >= maxAttempts) {
				setError('Processing timeout. Please check the dashboard.');
				setUploading(false);
				return;
			}

			try {
				const response = await fetch(window.teknupData.restUrl + `jobs/${jobId}`, {
					headers: {
						'X-WP-Nonce': window.teknupData.nonce
					}
				});
				const data = await response.json();

				setJob(data);

				if (data.status === 'completed') {
					setUploading(false);
					setProgress(100);
					fetchQuota(); // Refresh quota
				} else if (data.status === 'failed') {
					setError(data.error_message || 'Processing failed');
					setUploading(false);
				} else if (data.status === 'processing') {
					setProgress(50);
					attempts++;
					setTimeout(poll, 5000);
				} else {
					attempts++;
					setTimeout(poll, 5000);
				}
			} catch (err) {
				attempts++;
				setTimeout(poll, 5000);
			}
		};

		poll();
	};

	const resetForm = () => {
		setFile(null);
		setJob(null);
		setError(null);
		setProgress(0);
		setUploading(false);
		if (fileInputRef.current) {
			fileInputRef.current.value = '';
		}
	};

	const getDownloadUrl = (token, type = 'mastered') => {
		return `${window.teknupData.siteUrl}/wp-admin/admin-ajax.php?action=teknup_download&token=${token}&type=${type}`;
	};

	return (
		<div className="teknup-upload-container">
			<h2 className="teknup-heading">Stem Separation</h2>
			<p className="teknup-text-secondary" style={{ marginBottom: '24px' }}>
				Extract individual stems (vocals, drums, bass, other) from your techno track
			</p>

			{/* Quota Display */}
			{quota && (
				<div className="teknup-card" style={{ marginBottom: '24px' }}>
					<div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
						<div>
							<div className="teknup-text" style={{ fontWeight: 600, marginBottom: '4px' }}>
								{quota.plan_name}
							</div>
							<div className="teknup-text-secondary">
								{quota.unlimited ? 'Unlimited' : `${quota.remaining} stems remaining this month`}
							</div>
						</div>
					</div>
				</div>
			)}

			{!job && (
				<div className="teknup-card">
					{/* File Upload Area */}
					<div
						className="teknup-upload-area"
						onDrop={handleDrop}
						onDragOver={handleDragOver}
						onClick={() => fileInputRef.current?.click()}
					>
						<input
							ref={fileInputRef}
							type="file"
							accept="audio/*"
							onChange={handleFileChange}
							style={{ display: 'none' }}
						/>
						<div className="teknup-upload-icon">🎵</div>
						<div className="teknup-upload-text">
							{file ? file.name : 'Drop your audio file here or click to browse'}
						</div>
						<div className="teknup-upload-subtext">
							Supports WAV, MP3, FLAC, and more
						</div>
					</div>

					{/* Settings */}
					{file && (
						<div style={{ marginTop: '24px', padding: '16px', backgroundColor: '#f8f9fa', borderRadius: '8px' }}>
							<h3 className="teknup-text" style={{ marginBottom: '16px', fontWeight: 600 }}>
								Processing Settings
							</h3>

							<div style={{ marginTop: '16px' }}>
								<label className="teknup-label">Processing Intensity</label>
								<select
									className="teknup-select"
									value={settings.intensity}
									onChange={(e) => setSettings({ ...settings, intensity: e.target.value })}
								>
									<option value="low">Low - Minimal processing</option>
									<option value="medium">Medium - Balanced</option>
									<option value="high">High - Maximum quality (recommended)</option>
								</select>
							</div>

							<div style={{ marginTop: '16px' }}>
								<label className="teknup-label">Techno Style</label>
								<select
									className="teknup-select"
									value={settings.genre}
									onChange={(e) => setSettings({ ...settings, genre: e.target.value })}
								>
									<option value="techno">Techno</option>
									<option value="minimal_techno">Minimal Techno</option>
									<option value="hard_techno">Hard Techno</option>
									<option value="industrial_techno">Industrial Techno</option>
									<option value="melodic_techno">Melodic Techno</option>
									<option value="acid_techno">Acid Techno</option>
								</select>
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
									disabled={uploading}
								>
									{uploading ? 'Processing...' : 'Extract Stems'}
								</button>
								<button
									className="teknup-button-secondary"
									onClick={resetForm}
									disabled={uploading}
								>
									Cancel
								</button>
							</div>
						</div>
					)}

					{error && (
						<div className="teknup-alert teknup-alert-error" style={{ marginTop: '16px' }}>
							{error}
						</div>
					)}
				</div>
			)}

			{/* Processing Status */}
			{job && uploading && (
				<div className="teknup-card">
					<h3 className="teknup-text" style={{ marginBottom: '16px' }}>
						Processing your track...
					</h3>
					<div className="teknup-progress-bar">
						<div
							className="teknup-progress-fill"
							style={{ width: `${progress}%` }}
						></div>
					</div>
					<p className="teknup-text-secondary" style={{ marginTop: '16px', textAlign: 'center' }}>
						Status: {job.status}
					</p>
					<p className="teknup-text-secondary" style={{ marginTop: '8px', textAlign: 'center', fontSize: '14px' }}>
						This may take 2-5 minutes. Extracting stems with AI processing...
					</p>
				</div>
			)}

			{/* Completion */}
			{job && job.status === 'completed' && (
				<div className="teknup-card">
					<div style={{ textAlign: 'center', padding: '24px' }}>
						<div style={{ fontSize: '48px', marginBottom: '16px' }}>✅</div>
						<h3 className="teknup-text" style={{ marginBottom: '16px', fontWeight: 600 }}>
							Stems Ready!
						</h3>
						<p className="teknup-text-secondary" style={{ marginBottom: '24px' }}>
							Your stems have been extracted and processed successfully.
						</p>

						{/* Download Stems */}
						{job.stems && job.stems.length > 0 && (
							<div style={{ marginBottom: '24px' }}>
								<h4 className="teknup-text" style={{ marginBottom: '12px' }}>Download Stems:</h4>
								<div style={{ display: 'flex', flexDirection: 'column', gap: '8px', maxWidth: '400px', margin: '0 auto' }}>
									{job.stems.map((stem, index) => (
										<a
											key={index}
											href={stem.url}
											className="teknup-button-secondary"
											style={{ textDecoration: 'none', padding: '12px' }}
										>
											📥 Download {stem.name}
										</a>
									))}
								</div>
							</div>
						)}

						{/* Download Full Mix */}
						{job.download_url && (
							<div style={{ marginBottom: '24px' }}>
								<h4 className="teknup-text" style={{ marginBottom: '12px' }}>Full Processed Mix:</h4>
								<a
									href={job.download_url}
									className="teknup-button"
									style={{ textDecoration: 'none', display: 'inline-block' }}
								>
									📥 Download Full Mix
								</a>
							</div>
						)}

						<button
							className="teknup-button-secondary"
							onClick={resetForm}
							style={{ marginTop: '16px' }}
						>
							Process Another Track
						</button>
					</div>
				</div>
			)}
		</div>
	);
};

export default StemSeparation;
