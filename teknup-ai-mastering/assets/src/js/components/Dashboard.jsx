import React, { useState, useEffect } from 'react';
import JobsList from './JobsList';

const Dashboard = () => {
	const [quota, setQuota] = useState(null);
	const [jobs, setJobs] = useState([]);
	const [loading, setLoading] = useState(true);

	useEffect(() => {
		fetchData();
	}, []);

	const fetchData = async () => {
		setLoading(true);

		try {
			// Fetch quota
			const quotaResponse = await fetch(window.teknupData.restUrl + 'quota', {
				headers: {
					'X-WP-Nonce': window.teknupData.nonce
				}
			});
			const quotaData = await quotaResponse.json();
			setQuota(quotaData);

			// Fetch recent jobs
			const jobsResponse = await fetch(window.teknupData.restUrl + 'jobs?per_page=10', {
				headers: {
					'X-WP-Nonce': window.teknupData.nonce
				}
			});
			const jobsData = await jobsResponse.json();
			setJobs(jobsData.jobs || []);
		} catch (err) {
			console.error('Failed to fetch data:', err);
		} finally {
			setLoading(false);
		}
	};

	const handleJobDelete = async (jobId) => {
		if (!confirm('Are you sure you want to delete this job?')) return;

		try {
			await fetch(window.teknupData.restUrl + `jobs/${jobId}`, {
				method: 'DELETE',
				headers: {
					'X-WP-Nonce': window.teknupData.nonce
				}
			});

			// Refresh jobs list
			fetchData();
		} catch (err) {
			console.error('Failed to delete job:', err);
			alert('Failed to delete job. Please try again.');
		}
	};

	if (loading) {
		return (
			<div className="teknup-dashboard-container">
				<div className="teknup-spinner"></div>
			</div>
		);
	}

	return (
		<div className="teknup-dashboard-container">
			<h2 className="teknup-heading">Dashboard</h2>

			{/* Quota Stats */}
			<div className="teknup-stats-grid">
				<div className="teknup-stat-card">
					<div className="teknup-stat-value">{quota?.plan_name || 'N/A'}</div>
					<div className="teknup-stat-label">Current Plan</div>
				</div>

				<div className="teknup-stat-card">
					<div className="teknup-stat-value">
						{quota?.unlimited ? '∞' : quota?.remaining || 0}
					</div>
					<div className="teknup-stat-label">Masters Remaining</div>
				</div>

				<div className="teknup-stat-card">
					<div className="teknup-stat-value">{quota?.usage || 0}</div>
					<div className="teknup-stat-label">Used This Month</div>
				</div>

				<div className="teknup-stat-card">
					<div className="teknup-stat-value">{jobs.length}</div>
					<div className="teknup-stat-label">Total Jobs</div>
				</div>
			</div>

			{/* Quota Progress Bar */}
			{quota && !quota.unlimited && (
				<div className="teknup-card" style={{ marginBottom: '24px' }}>
					<h3 className="teknup-text">Monthly Usage</h3>
					<div className="teknup-progress-bar" style={{ marginTop: '16px' }}>
						<div
							className="teknup-progress-fill"
							style={{ width: `${quota.percentage}%` }}
						></div>
					</div>
					<p className="teknup-text-secondary" style={{ marginTop: '8px' }}>
						{quota.usage} / {quota.limit} masters used ({quota.percentage}%)
					</p>
				</div>
			)}

			{/* Recent Jobs */}
			<div className="teknup-card">
				<h3 className="teknup-heading" style={{ fontSize: '20px' }}>Recent Jobs</h3>

				{jobs.length === 0 ? (
					<p className="teknup-text-secondary" style={{ marginTop: '16px' }}>
						No jobs yet. Upload your first track to get started!
					</p>
				) : (
					<JobsList jobs={jobs} onDelete={handleJobDelete} />
				)}
			</div>
		</div>
	);
};

export default Dashboard;
