import { createRoot, StrictMode, useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import "./scss/maintenance.scss";

const PostsMaintenance = () => {
    const [postTypes, setPostTypes] = useState([]);
    const [selectedPostTypes, setSelectedPostTypes] = useState(['post', 'page']);
    const [isScanning, setIsScanning] = useState(false);
    const [progress, setProgress] = useState(null);

    useEffect(() => {
        setPostTypes(wpmudevPostsMaintenance.postTypes);
        checkExistingScan();
    }, []);

    const checkExistingScan = async () => {
        try {
            const formData = new FormData();
            formData.append('action', 'wpmudev_check_scan_status');
            formData.append('nonce', wpmudevPostsMaintenance.ajax_nonce);

            const response = await apiFetch({
                url: wpmudevPostsMaintenance.ajaxurl,
                method: 'POST',
                body: formData
            });

            if (response.success && response.data.status === 'processing') {
                setIsScanning(true);
                setProgress(response.data);
                monitorScanProgress();
            }
        } catch (error) {
            console.error('Error checking scan status:', error);
        }
    };

    const startScan = async () => {
        setIsScanning(true);
        setProgress({ 
            message: __('Starting scan...', 'wpmudev-plugin-test'),
            percentage: 0 
        });

        try {
            const formData = new FormData();
            formData.append('action', 'wpmudev_start_maintenance_scan');
            formData.append('nonce', wpmudevPostsMaintenance.ajax_nonce);
            
            // Append each post type individually
            selectedPostTypes.forEach(postType => {
                formData.append('post_types[]', postType);
            });

            const response = await apiFetch({
                url: wpmudevPostsMaintenance.ajaxurl,
                method: 'POST',
                body: formData
            });

            if (response.success) {
                setProgress({
                    message: __('Scan in progress...', 'wpmudev-plugin-test'),
                    total: response.data.total,
                    processed: 0,
                    percentage: 0
                });
                monitorScanProgress();
            } else {
                throw new Error(response.data || __('Scan failed to start', 'wpmudev-plugin-test'));
            }
        } catch (error) {
            setIsScanning(false);
            setProgress(null);
            alert(__('Error starting scan: ', 'wpmudev-plugin-test') + error.message);
        }
    };

    const monitorScanProgress = async () => {
        const checkProgress = async () => {
            try {
                const formData = new FormData();
                formData.append('action', 'wpmudev_check_scan_status');
                formData.append('nonce', wpmudevPostsMaintenance.ajax_nonce);

                const response = await apiFetch({
                    url: wpmudevPostsMaintenance.ajaxurl,
                    method: 'POST',
                    body: formData
                });

                if (response.success) {
                    setProgress(response.data);
                    
                    if (response.data.status === 'completed') {
                        setIsScanning(false);
                        alert(__('Scan completed successfully!', 'wpmudev-plugin-test'));
                    } else if (response.data.status === 'processing') {
                        setTimeout(checkProgress, 2000);
                    } else {
                        // If status is not processing or completed, stop scanning
                        setIsScanning(false);
                    }
                }
            } catch (error) {
                console.error('Error checking progress:', error);
                setIsScanning(false);
                setProgress(null);
                alert(__('Error checking scan progress: ', 'wpmudev-plugin-test') + error.message);
            }
        };

        checkProgress();
    };

    const handlePostTypeChange = (e) => {
        const selectedOptions = Array.from(e.target.selectedOptions, option => option.value);
        setSelectedPostTypes(selectedOptions);
    };

    const stopScan = async () => {
        try {
            // Add a way to stop the scan if needed
            setIsScanning(false);
            setProgress(null);
            alert(__('Scan stopped by user', 'wpmudev-plugin-test'));
        } catch (error) {
            console.error('Error stopping scan:', error);
        }
    };

    return (
        <div className="sui-box">
            <div className="sui-box-header">
                <h2>{__('Posts Maintenance', 'wpmudev-plugin-test')}</h2>
            </div>
            <div className="sui-box-body">
                <div className="sui-form-field">
                    <label className="sui-label">{__('Select Post Types:', 'wpmudev-plugin-test')}</label>
                    <select
                        multiple
                        value={selectedPostTypes}
                        onChange={handlePostTypeChange}
                        disabled={isScanning}
                        className="sui-select"
                        style={{ height: '120px' }}
                    >
                        {postTypes.map(postType => (
                            <option key={postType.value} value={postType.value}>
                                {postType.label}
                            </option>
                        ))}
                    </select>
                    <span className="sui-description">
                        {__('Hold Ctrl/Cmd to select multiple post types', 'wpmudev-plugin-test')}
                    </span>
                </div>
                
                <div className="sui-form-field" style={{ marginTop: '20px' }}>
                    {!isScanning ? (
                        <button
                            className="sui-button sui-button-primary"
                            onClick={startScan}
                            disabled={selectedPostTypes.length === 0}
                        >
                            {__('Scan Posts', 'wpmudev-plugin-test')}
                        </button>
                    ) : (
                        <button
                            className="sui-button sui-button-ghost"
                            onClick={stopScan}
                        >
                            {__('Stop Scan', 'wpmudev-plugin-test')}
                        </button>
                    )}
                </div>
                
                {progress && (
                    <div className="sui-notice sui-notice-info" style={{ marginTop: '20px' }}>
                        <div className="sui-notice-content">
                            <div className="sui-notice-message">
                                <span className="sui-notice-icon sui-icon-info sui-md" aria-hidden="true"></span>
                                <p>{progress.message}</p>
                                {progress.total > 0 && (
                                    <div className="sui-progress-block">
                                        <div className="sui-progress">
                                            <span className="sui-progress-text">{progress.percentage}%</span>
                                            <div className="sui-progress-bar">
                                                <span 
                                                    className="sui-progress-bar-value" 
                                                    style={{ width: `${progress.percentage}%` }}
                                                ></span>
                                            </div>
                                        </div>
                                        {progress.total && (
                                            <p>
                                                {__('Processed: ', 'wpmudev-plugin-test')} 
                                                {progress.processed} / {progress.total}
                                            </p>
                                        )}
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};

document.addEventListener('DOMContentLoaded', () => {
    const rootElement = document.getElementById(wpmudevPostsMaintenance.dom_element_id);
    if (rootElement) {
        const root = createRoot(rootElement);
        root.render(
            <StrictMode>
                <PostsMaintenance />
            </StrictMode>
        );
    }
});