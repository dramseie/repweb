import React from 'react';

export default class ErrorBoundary extends React.Component {
  constructor(props) {
    super(props);
    this.state = { hasError: false, error: null };
  }

  static getDerivedStateFromError(error) {
    return { hasError: true, error };
  }

  componentDidCatch(error, info) {
    // Optional: send to your logger
    if (this.props.onError) this.props.onError(error, info);
    // console.error('ErrorBoundary caught:', error, info);
  }

  handleReset = () => {
    this.setState({ hasError: false, error: null });
    if (this.props.onReset) this.props.onReset();
  };

  render() {
    if (this.state.hasError) {
      const Fallback = this.props.fallback;
      if (Fallback) {
        return (
          <Fallback
            error={this.state.error}
            onReset={this.handleReset}
          />
        );
      }
      return (
        <div className="alert alert-danger">
          <div className="d-flex align-items-start gap-2">
            <i className="bi bi-exclamation-triangle-fill"></i>
            <div>
              <strong>Something went wrong.</strong>
              <div className="small text-muted">{String(this.state.error)}</div>
              <button className="btn btn-sm btn-outline-danger mt-2" onClick={this.handleReset}>
                Try again
              </button>
            </div>
          </div>
        </div>
      );
    }
    return this.props.children;
  }
}
