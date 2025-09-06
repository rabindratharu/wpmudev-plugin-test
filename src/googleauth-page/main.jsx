import { createRoot, render, StrictMode, createInterpolateElement, useState, useEffect } from '@wordpress/element';
import { Button, TextControl, Snackbar, Spinner } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

import "./scss/style.scss"

const domElement = document.getElementById(window.wpmudevPluginTest.dom_element_id);

const WPMUDEV_PluginTest = () => {
    const [clientId, setClientId] = useState('');
    const [clientSecret, setClientSecret] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const [isLoadingData, setIsLoadingData] = useState(true);
    const [snackbar, setSnackbar] = useState(null);
    const [isError, setIsError] = useState(false);

    // Load saved credentials on component mount
    useEffect(() => {
        /**
         * Loads saved Google credentials.
         *
         * @async
         *
         * @returns {Promise<void>}
         *
         * @since 1.0.0
         */
        const loadCredentials = async () => {
            try {
                const response = await apiFetch({
                    path: '/wpmudev/v1/auth/auth-url',
                    method: 'GET',
                });

                if (response.success) {
                    setClientId(response.client_id || '');
                    setClientSecret(response.client_secret || '');
                }
            } catch (error) {
                console.error('Failed to load credentials:', error);
                showSnackbar(__('Failed to load saved credentials.', 'wpmudev-plugin-test'), true);
            } finally {
                setIsLoadingData(false);
            }
        };

        loadCredentials();
    }, []);

    /**
     * Shows a snackbar message that auto-dismisses after 3 seconds.
     *
     * @param {string} message The message to display.
     * @param {boolean} isError Whether the message is an error.
     *
     * @since 1.0.0
     */
    const showSnackbar = (message, isError = false) => {
        setSnackbar({ message, isError });
        setIsError(isError);

        // Auto-dismiss after 3 seconds
        setTimeout(() => {
            setSnackbar(null);
        }, 3000);
    };

    /**
     * Handles saving of the Google credentials.
     *
     * @async
     *
     * @returns {Promise<void>}
     *
     * @since 1.0.0
     */
    const handleSave = async () => {
        setIsLoading(true);
        setSnackbar(null);
        setIsError(false);

        try {
            const response = await apiFetch({
                path: '/wpmudev/v1/auth/auth-url',
                method: 'POST',
                data: {
                    client_id: clientId,
                    client_secret: clientSecret,
                },
            });

            if (response.success) {
                showSnackbar(__('Credentials saved successfully!', 'wpmudev-plugin-test'), false);
            } else {
                showSnackbar(response.message || __('Error saving credentials.', 'wpmudev-plugin-test'), true);
            }
        } catch (error) {
            showSnackbar(error.message || __('An error occurred while saving credentials.', 'wpmudev-plugin-test'), true);
        } finally {
            setIsLoading(false);
        }
    }

    if (isLoadingData) {
        return (
            <>
                <div className="sui-header">
                    <h1 className="sui-header-title">
                        {__('Settings', 'wpmudev-plugin-test')}
                    </h1>
                </div>
                <div className="sui-box">
                    <div className="sui-box-body">
                        <div style={{ textAlign: 'center', padding: '2rem' }}>
                            <Spinner />
                            <p>{__('Loading credentials...', 'wpmudev-plugin-test')}</p>
                        </div>
                    </div>
                </div>
            </>
        );
    }

    return (
        <>
            <div className="sui-header">
                <h1 className="sui-header-title">
                    {__('Settings', 'wpmudev-plugin-test')}
                </h1>
            </div>

            <div className="sui-box">

                <div className="sui-box-header">
                    <h2 className="sui-box-title">
                        {__('Set Google credentials', 'wpmudev-plugin-test')}
                    </h2>
                </div>

                <div className="sui-box-body">
                    <div className="sui-box-settings-row">
                        <TextControl
                            help={createInterpolateElement(
                                __('You can get Client ID from <a>here</a>.', 'wpmudev-plugin-test'),
                                {
                                    a: <a href="https://developers.google.com/identity/gsi/web/guides/get-google-api-clientid" />,
                                }
                            )}
                            label={__('Client ID', 'wpmudev-plugin-test')}
                            value={clientId}
                            onChange={setClientId}
                            disabled={isLoading}
                            placeholder={__('Enter your Google Client ID', 'wpmudev-plugin-test')}
                        />
                    </div>

                    <div className="sui-box-settings-row">
                        <TextControl
                            help={createInterpolateElement(
                                __('You can get Client Secret from <a>here</a>.', 'wpmudev-plugin-test'),
                                {
                                    a: <a href="https://developers.google.com/identity/gsi/web/guides/get-google-api-clientid" />,
                                }
                            )}
                            label={__('Client Secret', 'wpmudev-plugin-test')}
                            value={clientSecret}
                            onChange={setClientSecret}
                            type="password"
                            disabled={isLoading}
                            placeholder={__('Enter your Google Client Secret', 'wpmudev-plugin-test')}
                        />
                    </div>

                    <div className="sui-box-settings-row">
                        <span>
                            {__('Please use this url', 'wpmudev-plugin-test')}{' '}
                            <em>{window.wpmudevPluginTest.returnUrl}</em>{' '}
                            {__('in your Google API\'s', 'wpmudev-plugin-test')}{' '}
                            <strong>{__('Authorized redirect URIs', 'wpmudev-plugin-test')}</strong>{' '}
                            {__('field', 'wpmudev-plugin-test')}
                        </span>
                    </div>
                </div>

                <div className="sui-box-footer">
                    <div className="sui-actions-right">
                        <Button
                            variant="primary"
                            onClick={handleSave}
                            disabled={isLoading || !clientId || !clientSecret}
                        >
                            {isLoading ? __('Saving...', 'wpmudev-plugin-test') : __('Save', 'wpmudev-plugin-test')}
                        </Button>
                    </div>
                </div>

            </div>

            {/* Snackbar notification */}
            {snackbar && (
                <Snackbar
                    className={snackbar.isError ? 'is-error' : 'is-success'}
                    onRemove={() => setSnackbar(null)}
                >
                    {snackbar.message}
                </Snackbar>
            )}
        </>
    );
}

if (createRoot) {
    createRoot(domElement).render(<StrictMode><WPMUDEV_PluginTest /></StrictMode>);
} else {
    render(<StrictMode><WPMUDEV_PluginTest /></StrictMode>, domElement);
}