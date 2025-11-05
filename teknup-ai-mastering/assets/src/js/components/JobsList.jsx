import React from 'react';

const JobsList = ({ jobs, onDelete }) => {
	const formatDate = (dateString) => {
		if (!dateString) return 'N/A';
		const date = new Date(dateString);
		return date.toLocaleString();
	};

	const formatFileSize = (bytes) => {
		if (!bytes) return 'N/A';
		return (bytes / 1024 / 1024).toFixed(2) + ' MB';
	};

	return (
		<div className="teknup-jobs-list">
			{jobs.map((job) => (
				<div key={job.id} className="teknup-job-item teknup-fade-in">
					<div className="teknup-job-header">
						<div>
							<div className="teknup-job-title">{job.original_filename}</div>
							<div className="teknup-job-meta">
								<span>{formatFileSize(job.file_size)}</span>
								<span>{formatDate(job.created_at)}</span>
							</div>
						</div>
						<span className={`teknup-status-badge teknup-status-${job.status}`}>
							{job.status.replace('_', ' ').toUpperCase()}
						</span>
					</div>

					{job.intensity && (
						<p className="teknup-text-secondary" style={{ marginTop: '8px' }}>
							Intensity: {job.intensity} | LUFS: {job.target_lufs}
						</p>
					)}

					{job.processing_time && (
						<p className="teknup-text-secondary">
							Processing time: {job.processing_time}s
						</p>
					)}

					{job.error_message && (
						<p style={{ color: '#F44336', marginTop: '8px' }}>
							Error: {job.error_message}
						</p>
					)}

					<div className="teknup-job-actions">
						{job.status === 'completed' && job.download_url && (
							<a
								href={job.download_url}
								className="teknup-button"
								download
								style={{ fontSize: '14px', padding: '8px 16px' }}
							>
								Download
							</a>
						)}

						<button
							className="teknup-button teknup-button-secondary"
							onClick={() => onDelete(job.id)}
							style={{ fontSize: '14px', padding: '8px 16px' }}
						>
							Delete
						</button>
					</div>
				</div>
			))}
		</div>
	);
};

export default JobsList;
