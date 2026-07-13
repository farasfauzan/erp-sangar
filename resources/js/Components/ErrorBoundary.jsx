import { Component } from 'react';

export default class ErrorBoundary extends Component {
    constructor(props) {
        super(props);
        this.state = { hasError: false, error: null };
    }

    static getDerivedStateFromError(error) {
        return { hasError: true, error };
    }

    componentDidCatch(error, errorInfo) {
        console.error('ErrorBoundary caught an error:', error, errorInfo);
    }

    handleRetry = () => {
        this.setState({ hasError: false, error: null });
    };

    render() {
        if (this.state.hasError) {
            if (this.props.fallback) {
                return this.props.fallback;
            }

            return (
                <div style={{ padding: '2rem', maxWidth: '32rem', margin: '2.5rem auto', background: '#fdf6ee', border: '2px solid #b8860b', borderRadius: '8px', color: '#5a1a0a' }}>
                    <h2 style={{ fontSize: '1.1rem', fontWeight: 700, marginBottom: '0.5rem' }}>Terjadi Kesalahan</h2>
                    <p style={{ fontSize: '0.85rem', fontWeight: 600, marginBottom: '1rem' }}>{this.state.error?.toString()}</p>
                    <button
                        onClick={this.handleRetry}
                        style={{
                            padding: '0.5rem 1.25rem',
                            background: 'linear-gradient(180deg, #8b2e1e 0%, #6b1a10 100%)',
                            color: '#fef0d8',
                            fontSize: '0.85rem',
                            fontWeight: 600,
                            borderRadius: '3px',
                            border: '1px solid #6b1a10',
                            cursor: 'pointer',
                            marginBottom: '1rem',
                            display: 'inline-block',
                        }}
                    >
                        Coba Lagi
                    </button>
                    <pre style={{ fontSize: '0.7rem', overflow: 'auto', maxHeight: '16rem', background: '#f5e6d0', padding: '0.75rem', borderRadius: '4px', fontFamily: 'monospace' }}>{this.state.error?.stack}</pre>
                </div>
            );
        }

        return this.props.children;
    }
}
