import React, { useState } from 'react';
import SubscriptionPlans from './SubscriptionPlans';

const Auth = ({ onAuthSuccess }) => {
	const [activeTab, setActiveTab] = useState('login');
	const [loading, setLoading] = useState(false);
	const [error, setError] = useState('');
	const [success, setSuccess] = useState('');

	// Login form state
	const [loginData, setLoginData] = useState({
		username: '',
		password: '',
	});

	// Register form state
	const [registerData, setRegisterData] = useState({
		username: '',
		email: '',
		password: '',
		confirmPassword: '',
	});

	const handleLogin = async (e) => {
		e.preventDefault();
		setError('');
		setLoading(true);

		try {
			const response = await fetch(teknupData.restUrl + 'auth/login', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
				},
				body: JSON.stringify(loginData),
			});

			const data = await response.json();

			if (!response.ok) {
				throw new Error(data.message || 'Login failed');
			}

			setSuccess('Successfully logged in! Redirecting...');
			setTimeout(() => {
				window.location.reload();
			}, 1000);
		} catch (err) {
			setError(err.message);
		} finally {
			setLoading(false);
		}
	};

	const handleRegister = async (e) => {
		e.preventDefault();
		setError('');

		// Validation
		if (registerData.password !== registerData.confirmPassword) {
			setError('Passwords do not match');
			return;
		}

		if (registerData.password.length < 6) {
			setError('Password must be at least 6 characters');
			return;
		}

		setLoading(true);

		try {
			const response = await fetch(teknupData.restUrl + 'auth/register', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
				},
				body: JSON.stringify({
					username: registerData.username,
					email: registerData.email,
					password: registerData.password,
				}),
			});

			const data = await response.json();

			if (!response.ok) {
				throw new Error(data.message || 'Registration failed');
			}

			setSuccess('Account created successfully! Logging you in...');
			setTimeout(() => {
				window.location.reload();
			}, 1500);
		} catch (err) {
			setError(err.message);
		} finally {
			setLoading(false);
		}
	};

	return (
		<div className="teknup-auth-container">
			<div className="teknup-auth-header">
				<h2>Teknup AI Mastering</h2>
				<p>Professional audio mastering by AI</p>
			</div>

			{/* Tabs Navigation */}
			<div className="teknup-auth-tabs">
				<button
					className={`teknup-auth-tab ${activeTab === 'login' ? 'active' : ''}`}
					onClick={() => {
						setActiveTab('login');
						setError('');
						setSuccess('');
					}}
				>
					Login
				</button>
				<button
					className={`teknup-auth-tab ${activeTab === 'register' ? 'active' : ''}`}
					onClick={() => {
						setActiveTab('register');
						setError('');
						setSuccess('');
					}}
				>
					Create Account
				</button>
				<button
					className={`teknup-auth-tab ${activeTab === 'plans' ? 'active' : ''}`}
					onClick={() => {
						setActiveTab('plans');
						setError('');
						setSuccess('');
					}}
				>
					Plans & Pricing
				</button>
			</div>

			{/* Messages */}
			{error && (
				<div className="teknup-message teknup-message-error">
					{error}
				</div>
			)}
			{success && (
				<div className="teknup-message teknup-message-success">
					{success}
				</div>
			)}

			{/* Tab Content */}
			<div className="teknup-auth-content">
				{/* Login Tab */}
				{activeTab === 'login' && (
					<form onSubmit={handleLogin} className="teknup-auth-form">
						<div className="teknup-form-group">
							<label htmlFor="login-username">Username or Email</label>
							<input
								type="text"
								id="login-username"
								className="teknup-input"
								value={loginData.username}
								onChange={(e) => setLoginData({ ...loginData, username: e.target.value })}
								required
								disabled={loading}
							/>
						</div>

						<div className="teknup-form-group">
							<label htmlFor="login-password">Password</label>
							<input
								type="password"
								id="login-password"
								className="teknup-input"
								value={loginData.password}
								onChange={(e) => setLoginData({ ...loginData, password: e.target.value })}
								required
								disabled={loading}
							/>
						</div>

						<button
							type="submit"
							className="teknup-button teknup-button-primary"
							disabled={loading}
						>
							{loading ? 'Logging in...' : 'Login'}
						</button>

						<p className="teknup-auth-footer">
							Don't have an account?{' '}
							<a href="#" onClick={(e) => { e.preventDefault(); setActiveTab('register'); }}>
								Create one
							</a>
						</p>
					</form>
				)}

				{/* Register Tab */}
				{activeTab === 'register' && (
					<form onSubmit={handleRegister} className="teknup-auth-form">
						<div className="teknup-form-group">
							<label htmlFor="register-username">Username</label>
							<input
								type="text"
								id="register-username"
								className="teknup-input"
								value={registerData.username}
								onChange={(e) => setRegisterData({ ...registerData, username: e.target.value })}
								required
								disabled={loading}
								minLength={3}
							/>
							<small>Minimum 3 characters</small>
						</div>

						<div className="teknup-form-group">
							<label htmlFor="register-email">Email</label>
							<input
								type="email"
								id="register-email"
								className="teknup-input"
								value={registerData.email}
								onChange={(e) => setRegisterData({ ...registerData, email: e.target.value })}
								required
								disabled={loading}
							/>
						</div>

						<div className="teknup-form-group">
							<label htmlFor="register-password">Password</label>
							<input
								type="password"
								id="register-password"
								className="teknup-input"
								value={registerData.password}
								onChange={(e) => setRegisterData({ ...registerData, password: e.target.value })}
								required
								disabled={loading}
								minLength={6}
							/>
							<small>Minimum 6 characters</small>
						</div>

						<div className="teknup-form-group">
							<label htmlFor="register-confirm-password">Confirm Password</label>
							<input
								type="password"
								id="register-confirm-password"
								className="teknup-input"
								value={registerData.confirmPassword}
								onChange={(e) => setRegisterData({ ...registerData, confirmPassword: e.target.value })}
								required
								disabled={loading}
							/>
						</div>

						<button
							type="submit"
							className="teknup-button teknup-button-primary"
							disabled={loading}
						>
							{loading ? 'Creating Account...' : 'Create Account'}
						</button>

						<p className="teknup-auth-footer">
							Already have an account?{' '}
							<a href="#" onClick={(e) => { e.preventDefault(); setActiveTab('login'); }}>
								Login
							</a>
						</p>
					</form>
				)}

				{/* Plans Tab */}
				{activeTab === 'plans' && (
					<div className="teknup-plans-wrapper">
						<p className="teknup-plans-intro">
							Choose the plan that best fits your needs. You can upgrade or downgrade at any time.
						</p>
						<SubscriptionPlans />
						<p className="teknup-auth-footer" style={{ textAlign: 'center', marginTop: '30px' }}>
							Already subscribed?{' '}
							<a href="#" onClick={(e) => { e.preventDefault(); setActiveTab('login'); }}>
								Login to your account
							</a>
						</p>
					</div>
				)}
			</div>
		</div>
	);
};

export default Auth;
