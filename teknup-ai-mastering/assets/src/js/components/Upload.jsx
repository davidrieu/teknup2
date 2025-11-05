import React, { useState, useRef } from 'react';

const Upload = () => {
	const [file, setFile] = useState(null);
	const [uploading, setUploading] = useState(false);
	const [progress, setProgress] = useState(0);
	const [job, setJob] = useState(null);
	const [error, setError] = useState(null);
	const [quota, setQuota] = useState(null);
	const [settings, setSettings] = useState({
		intensity: 'medium',
		genre: '',
		target_lufs: -14
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
								<select
									className="teknup-select"
									value={settings.intensity}
									onChange={(e) => setSettings({ ...settings, intensity: e.target.value })}
								>
									<option value="light">Light</option>
									<option value="medium">Medium</option>
									<option value="heavy">Heavy</option>
								</select>
							</div>

							<div style={{ marginTop: '16px' }}>
								<label className="teknup-label">Genre (Optional)</label>
								<select
									className="teknup-select"
									value={settings.genre}
									onChange={(e) => setSettings({ ...settings, genre: e.target.value })}
								>
									<option value="">Auto Detect</option>
									<option value="techno">Techno</option>
									<option value="house">House</option>
									<option value="trance">Trance</option>
									<option value="dubstep">Dubstep</option>
									<option value="drum_and_bass">Drum & Bass</option>
									<option value="ambient">Ambient</option>
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
									Recommended: -14 for streaming, -9 for clubs
								</p>
							</div>

							<div style={{ marginTop: '24px', display: 'flex', gap: '16px' }}>
								<button
									className="teknup-button"
									onClick={handleUpload}
									disabled={uploading}
								>
									{uploading ? 'Processing...' : 'Start Mastering'}
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
						<div style={{ marginTop: '24px' }}>
							<a href={job.download_url} className="teknup-button" download>
								Download Mastered Track
							</a>
							<button
								className="teknup-button teknup-button-secondary"
								onClick={resetUpload}
								style={{ marginLeft: '16px' }}
							>
								Upload Another
							</button>
						</div>
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
		</div>
	);
};

export default Upload;
