var selectedRank = 0;
var selectedRankName = "";
var isGift = 0;

!function (XF, window, document, _undefined) {
    "use strict";

    function waitForStoreData(callback) {
        if (window.StoreData) {
            callback();
        } else {
            setTimeout(function () {
                waitForStoreData(callback);
            }, 50);
        }
    }

    XF.StorePackageSelect = XF.Element.newHandler({
        init: function () {
            var rankId = this.target.getAttribute("data-rank-id");
            var target = this.target;
            var hasHigherRankStatus = target.querySelector(".rank-status") && target.querySelector(".rank-status").textContent.includes("Higher Rank Active");

            if (hasHigherRankStatus) {
                target.style.cursor = "not-allowed";
                target.classList.add("rank-card-disabled");

                return;
            }

            target.style.cursor = "pointer";

            target.addEventListener("click", function (e) {
                if (e.target.closest(".gift-button")) {
                    return;
                }

                e.preventDefault();
                e.stopPropagation();

                if (target.classList.contains("rank-card-disabled")) {
                    return;
                }

                if (target.classList.contains("rank-card-selected")) {
                    resetStoreUI();
                } else {
                    selectedRank = rankId;
                    updateStoreUI(rankId);
                }
            });
        }
    });

    XF.StoreGiftButton = XF.Element.newHandler({
        init: function () {
            var target = this.target;

            target.addEventListener("click", function (e) {
                e.preventDefault();
                e.stopPropagation();

                if (!selectedRank || selectedRank === 0) {
                    XF.alert("Please select a rank first before gifting.");

                    return;
                }

                XF.loadOverlay(XF.canonicalizeUrl("store/userselect?rank_id=" + selectedRank));
            });
        }
    });

    var StoreApp = {
        selectedUser: 0,
        selectedRank: 0,
        recipientRankData: null,

        init: function () {
            var self = this;

            waitForStoreData(function () {
                self.selectedUser = window.StoreData.visitorId;

                var recipientElement = document.getElementById("selectedPackage-foruser");
                if (recipientElement && window.StoreData.visitorUsername) {
                    recipientElement.textContent = window.StoreData.visitorUsername;
                }

                self.updateResetButton();

                var resetButton = document.getElementById("reset-to-self-button");
                if (resetButton) {
                    resetButton.addEventListener("click", function (e) {
                        e.preventDefault();
                        self.resetToSelf();
                    });
                }

                if (!window.StoreData.isLoggedIn) {
                    self.showLoggedOutState();
                }

                var userSearchForm = document.getElementById("userSearch");
                if (userSearchForm) {
                    userSearchForm.addEventListener("submit", self.handleUserSearchSubmit.bind(self));
                }

                if (window.StoreData.isLoggedIn && typeof paypal !== "undefined") {
                    self.initPayPal();
                } else if (window.StoreData.isLoggedIn) {
                    var checkPayPal = setInterval(function () {
                        if (typeof paypal !== "undefined") {
                            clearInterval(checkPayPal);
                            self.initPayPal();
                        }
                    }, 100);
                }
            });
        },

        setStoreUser: function (userId, userName) {
            if (!window.StoreData.isLoggedIn) {
                return;
            }

            if (userId != null) {
                if (!window.StoreData.upgradeData[selectedRank]) {
                    XF.alert("Invalid package selection. Please refresh the page and try again.");

                    return;
                }

                this.selectedUser = userId;
                clearAllSelections();

                var verifiedUpgrade = window.StoreData.upgradeData[selectedRank];
                document.getElementById("selectedPackage-name").textContent = verifiedUpgrade.title;
                document.getElementById("selectedPackage-foruser").textContent = userName;
                document.getElementById("gift-action").style.display = "block";

                this.displayUpgradeDiscount(selectedRank, userName);

                document.getElementById("paypal-button").style.display = "block";
                document.getElementById("purchase-placeholder").style.display = "none";

                this.updateResetButton();

                var selectedCard = document.querySelector("[data-rank-id=\"" + selectedRank + "\"]");
                var hasOwnedStatus = selectedCard && selectedCard.querySelector(".rank-status") &&
                    selectedCard.querySelector(".rank-status").textContent.includes("Already Purchased");

                var giftNotice = document.getElementById("gift-notice");
                if (hasOwnedStatus) {
                    if (userId === window.StoreData.visitorId) {
                        // User selected themselves for a rank they already own
                        if (!giftNotice) {
                            giftNotice = document.createElement("div");
                            giftNotice.id = "gift-notice";
                            giftNotice.className = "message message--notice";
                            giftNotice.innerHTML = "<i class=\"fa fa-info-circle\"></i> You already own this rank. Use the gift button to purchase for another player.";
                            document.getElementById("selectedPackage-foruser").parentNode.insertBefore(giftNotice, document.getElementById("selectedPackage-foruser").nextSibling);
                        }
                        giftNotice.style.display = "block";
                    } else {
                        // User selected someone else for gifting
                        if (giftNotice) {
                            giftNotice.style.display = "none";
                        }
                    }
                } else {
                    // User doesn"t own this rank
                    if (giftNotice) {
                        giftNotice.style.display = "none";
                    }
                }

                var giftButton = document.getElementById("gift-button");
                if (giftButton) {
                    giftButton.setAttribute("data-rankid", selectedRank);
                }

                updateSelectedRankIcon(selectedRank);
                updateCardSelections(selectedRank);
            }
        },

        resetToSelf: function () {
            if (!window.StoreData.isLoggedIn) {
                return;
            }

            this.selectedUser = window.StoreData.visitorId;
            this.recipientRankData = null;

            document.getElementById("selectedPackage-foruser").textContent = window.StoreData.visitorUsername;
            this.updateResetButton();

            if (selectedRank && selectedRank !== 0) {
                updateStoreUI(selectedRank);
            }
        },

        updateResetButton: function () {
            updateResetButton();
        },

        isGiftedRank: function () {
            return (window.StoreData.visitorId != this.selectedUser) ? 1 : 0;
        },

        handleUserSearchSubmit: function (e) {
            e.stopPropagation();
            e.preventDefault();

            var form = e.target;
            var formData = new FormData(form);
            var data = {};

            for (var pair of formData.entries()) {
                data[pair[0]] = pair[1];
            }

            var self = this;
            XF.ajax("POST", form.action, data, function (response) {
                if (response && response.status === "ok") {
                    self.recipientRankData = {
                        userId: response.user_id,
                        userName: response.user_name,
                        highestRankPriority: response.highest_rank_priority || 0,
                        currentRankPrice: response.current_rank_price || 0
                    };
                    self.setStoreUser(response.user_id, response.user_name);
                } else if (response && response.errors) {
                    XF.alert("Error: " + response.errors.join(", "));
                } else {
                    XF.alert("Error processing request");
                }
            }).catch(function (error) {
                XF.alert("Error processing request: " + error.message);
            });
        },

        calculateUpgradePriceForRecipient: function (rankId) {
            // If purchasing for self, use visitor's info
            if (this.selectedUser === window.StoreData.visitorId) {
                return calculateUpgradePrice(rankId);
            }

            // If purchasing as gift and we have recipient info
            if (this.recipientRankData && window.StoreData.upgradeData && window.StoreData.upgradeData[rankId]) {
                var selectedUpgrade = window.StoreData.upgradeData[rankId];
                var basePrice = selectedUpgrade.price;
                var recipientCurrentPrice = this.recipientRankData.currentRankPrice || 0;
                var recipientHighestPriority = this.recipientRankData.highestRankPriority || 0;

                if (recipientHighestPriority > 0 && selectedUpgrade.rankPriority > recipientHighestPriority && recipientCurrentPrice > 0) {
                    var upgradePrice = basePrice - recipientCurrentPrice;
                    return Math.max(0, upgradePrice);
                }

                return basePrice;
            }

            // Default to base price if no data available
            return null;
        },

        /**
         * Displays upgrade discount pricing
         *
         * @param {number} rankId - Rank upgrade ID
         * @param {string} recipientName - Name of the recipient user
         * @description Updates UI to show upgrade discount pricing when applicable.
         *              Shows crossed-out original price, discounted price in green, and
         *              an upgrade notice with savings information. If no discount applies,
         *              displays the full price without styling.
         */
        displayUpgradeDiscount: function (rankId, recipientName) {
            if (!window.StoreData.upgradeData || !window.StoreData.upgradeData[rankId]) {
                return;
            }

            var verifiedUpgrade = window.StoreData.upgradeData[rankId];
            var upgradePrice = this.calculateUpgradePriceForRecipient(rankId);

            if (upgradePrice !== null && upgradePrice < verifiedUpgrade.price) {
                // Display crossed-out original price and green upgrade price
                var priceDisplay = "<span class=\"original-price\" style=\"text-decoration: line-through; color: #999; margin-right: 8px;\">$" + verifiedUpgrade.price.toFixed(2) + "</span>";
                priceDisplay += "<span class=\"upgrade-price\" style=\"color: #4caf50; font-weight: bold;\">$" + upgradePrice.toFixed(2) + "</span>";
                document.getElementById("selectedPackage-price").innerHTML = priceDisplay;

                // Show upgrade notice with discount
                var savings = verifiedUpgrade.price - upgradePrice;
                var upgradeNotice = document.getElementById("upgrade-notice");
                var tooltip = buildUpgradeTooltipMessage(savings, recipientName);
                var upgradeMessage = "<i class=\"fa fa-arrow-up\"></i> <span class=\"upgrade-discount-text\" data-xf-init=\"tooltip\" title=\"" + tooltip + "\">Upgrade Discount Applied!</span>";

                if (!upgradeNotice) {
                    upgradeNotice = document.createElement("div");
                    upgradeNotice.id = "upgrade-notice";
                    upgradeNotice.style.marginTop = "10px";
                    upgradeNotice.style.color = "#4caf50";

                    var priceElement = document.getElementById("selectedPackage-price");

                    if (priceElement && priceElement.parentNode) {
                        priceElement.parentNode.insertBefore(upgradeNotice, priceElement.nextSibling);
                    }
                }

                upgradeNotice.innerHTML = upgradeMessage;
                upgradeNotice.style.display = "block";

                XF.activate(upgradeNotice);
            } else {
                // No upgrade discount applies, show regular price
                document.getElementById("selectedPackage-price").textContent = "$" + verifiedUpgrade.price.toFixed(2);

                var upgradeNotice = document.getElementById("upgrade-notice");
                if (upgradeNotice) {
                    upgradeNotice.style.display = "none";
                }
            }
        },

        showLoggedOutState: function () {
            var purchaseBlock = document.querySelector(".purchase-summary-card .card-content");
            if (purchaseBlock) {
                purchaseBlock.innerHTML = "<div class=\"empty-state\"><p>You must be logged in to purchase a rank</p></div>";
            }

            var allCards = document.querySelectorAll(".rank-card");
            allCards.forEach(function (card) {
                card.classList.add("rank-card-disabled");
            });
        },

        initPayPal: function () {
            var self = this;

            paypal.Buttons({
                createOrder: function (data, actions) {
                    if (!selectedRank || selectedRank === 0) {
                        XF.alert("Please select a package before proceeding with payment.");
                        return Promise.reject(new Error("No package selected"));
                    }

                    if (!window.StoreData.upgradeData[selectedRank]) {
                        XF.alert("Invalid package selected. Please refresh the page and try again.");
                        return Promise.reject(new Error("Invalid upgrade ID"));
                    }

                    // Check if user is trying to buy a rank for themselves that they already own
                    var selectedCard = document.querySelector("[data-rank-id=\"" + selectedRank + "\"]");
                    var hasOwnedStatus = selectedCard && selectedCard.querySelector(".rank-status") &&
                        selectedCard.querySelector(".rank-status").textContent.includes("Already Purchased");

                    if (hasOwnedStatus && self.selectedUser === window.StoreData.visitorId) {
                        XF.alert("You already own this rank! You can only gift this rank to other players.");
                        return Promise.reject(new Error("Already owned rank"));
                    }

                    // Check if user is trying to buy a lower-tier rank for themselves
                    var isLowerTierForSelf = false;
                    if (window.StoreData && window.StoreData.upgradeData && window.StoreData.upgradeData[selectedRank] &&
                        self.selectedUser === window.StoreData.visitorId) {
                        var selectedUpgrade = window.StoreData.upgradeData[selectedRank];
                        var userHighestPriority = window.StoreData.userHighestRankPriority || 0;

                        if (userHighestPriority > 0 && selectedUpgrade.rankPriority <= userHighestPriority) {
                            isLowerTierForSelf = true;
                        }
                    }

                    if (isLowerTierForSelf) {
                        return Promise.reject(new Error("You cannot purchase this rank as you already own a higher-tier rank!"));
                    }

                    var basePrice = window.StoreData.upgradeData[selectedRank].price;
                    var verifiedTitle = window.StoreData.upgradeData[selectedRank].title;

                    // Calculate the actual price to charge (upgrade price for both self and gifts)
                    var isPurchasingForSelf = self.selectedUser === window.StoreData.visitorId;
                    var actualPrice = basePrice;
                    var isUpgrade = false;

                    if (isPurchasingForSelf) {
                        var calculatedPrice = calculateUpgradePrice(selectedRank);
                        if (calculatedPrice !== null && calculatedPrice < basePrice) {
                            actualPrice = calculatedPrice;
                            isUpgrade = true;
                        }
                    } else {
                        // For gifts, calculate upgrade price based on recipient's rank
                        var calculatedPrice = self.calculateUpgradePriceForRecipient(selectedRank);
                        if (calculatedPrice !== null && calculatedPrice < basePrice) {
                            actualPrice = calculatedPrice;
                            isUpgrade = true;
                        }
                    }

                    // Verify displayed price matches what we're about to charge
                    var displayedPriceElement = document.getElementById("selectedPackage-price");
                    var displayedPriceText = displayedPriceElement ? displayedPriceElement.textContent || displayedPriceElement.innerText : "";

                    // Extract the upgrade price if present (will be the last dollar amount shown)
                    var priceMatches = displayedPriceText.match(/\$[\d.]+/g);
                    var displayedPriceValue = 0;
                    if (priceMatches && priceMatches.length > 0) {
                        // Get the last price (which is the actual price to charge)
                        displayedPriceValue = parseFloat(priceMatches[priceMatches.length - 1].replace("$", ""));
                    }

                    if (Math.abs(displayedPriceValue - actualPrice) > 0.01) {
                        XF.alert("Price mismatch detected. Please refresh the page and try again.");

                        return Promise.reject(new Error("Price verification failed"));
                    }

                    var orderDescription;
                    if (self.selectedUser !== window.StoreData.visitorId) {
                        var recipientName = document.getElementById("selectedPackage-foruser").textContent;
                        orderDescription = "Gift Store Rank: " + verifiedTitle + " (for " + recipientName + ")";
                    } else if (isUpgrade) {
                        orderDescription = "Store Rank Upgrade: " + verifiedTitle;
                    } else {
                        orderDescription = "Store Rank: " + verifiedTitle;
                    }

                    // Call server to create order
                    return XF.ajax("POST", XF.canonicalizeUrl("store/createorder"), {
                        rank_id: selectedRank,
                        recipient_id: self.selectedUser,
                        is_gift: self.isGiftedRank()
                    }).then(function (response) {
                        if (response.data.orderId) {
                            return response.data.orderId;
                        } else {
                            throw new Error("Failed to create order. An Order ID was not returned");
                        }
                    });
                },

                onApprove: function (data, actions) {
                    return XF.ajax("POST", XF.canonicalizeUrl("store/captureorder"), {
                        order_id: data.orderID,
                        rank_id: selectedRank,
                        recipient_id: StoreApp.selectedUser,
                        is_gift: StoreApp.isGiftedRank()
                    }).then(function (response) {
                        if (!response || response.data.status !== "ok") {
                            // Store public controller will handle alert

                            return;
                        }

                        // TODO: All of this below I can do better with, but hey
                        // if it ain't broke, don't fix it.
                        if (response.data.html) {
                            XF.setupHtmlInsert(response.data.html, function (html, container, onComplete) {
                                var overlay = XF.showOverlay(XF.getOverlayHtml({
                                    title: "Purchase Complete",
                                    html: html,
                                    size: "large"
                                }));

                                XF.on(overlay.getContainer(), "overlay:hidden", function () {
                                    window.location.reload();
                                });
                            });
                        } else {
                            XF.alert("Purchase successful! Your rank has been activated.", function () {
                                window.location.reload();
                            });
                        }
                    }).catch(function (error) {
                        XF.alert("Something went wrong when processing your purchase: " + (error.message || error) + ". Order ID: " + data.orderID);
                    });
                },

                onError: function (err) {
                    XF.alert("Something went wrong when processing your purchase: " + err);
                }
            }).render("#paypal-button");
        },

        processStoreOrder: function (orderData, paypalOrderId) {
            var self = this;

            // console.log("Processing order with data:", orderData);
            // console.log("PayPal Order ID:", paypalOrderId);

            if (!orderData || !orderData.purchase_units || !orderData.purchase_units[0]) {
                XF.alert("Invalid order data received from PayPal. Please contact an Owner or Lead Administrator with order ID: " + paypalOrderId);
                return;
            }

            var purchaseUnit = orderData.purchase_units[0];

            if (!purchaseUnit.payments || !purchaseUnit.payments.captures || !purchaseUnit.payments.captures[0]) {
                XF.alert("Payment capture data is missing. Please contact an Owner or Lead Administrator with order ID: " + paypalOrderId);
                return;
            }

            var capture = purchaseUnit.payments.captures[0];

            var orderInfo = {
                paypal_order_id: paypalOrderId,
                rank_id: selectedRank,
                recipient_id: self.selectedUser,
                is_gift: self.isGiftedRank(),
                amount: purchaseUnit.amount ? purchaseUnit.amount.value : "0.00",
                currency: purchaseUnit.amount ? purchaseUnit.amount.currency_code : "USD",
                payer_id: orderData.payer ? orderData.payer.payer_id : "",
                transaction_id: capture.id || ""
            };

            XF.ajax("POST", XF.canonicalizeUrl("store/processpayment"), orderInfo, function (data) {
                if (data && data.status === "ok") {
                    if (data.html) {
                        XF.setupHtmlInsert(data.html, function (html, container, onComplete) {
                            var overlay = XF.getOverlayHtml({
                                title: "Purchase Complete",
                                html: html,
                                size: "large"
                            });

                            XF.showOverlay(overlay);
                        });
                    } else {
                        XF.alert("Purchase successful! Your rank has been activated.", function () {
                            if (data.redirect_url) {
                                window.location.href = data.redirect_url;
                            } else {
                                window.location.reload();
                            }
                        });
                    }
                } else if (data && data.errors) {
                    var errorMsg = data.errors.join(", ");
                    XF.alert("Payment was successful, but there was an issue activating your rank: " + errorMsg + ". Please contact an Owner or Lead Administrator with order ID: " + paypalOrderId);
                } else {
                    XF.alert("Payment was successful, but there was an issue activating your rank. Please contact an Owner or Lead Administrator with order ID: " + paypalOrderId);
                }
            }).catch(function (error) {
                XF.alert("Error processing your purchase. Please contact an Owner or Lead Administrator with order ID: " + paypalOrderId);
            });
        }
    };

    function calculateUpgradePrice(rankId) {
        if (!window.StoreData || !window.StoreData.upgradeData || !window.StoreData.upgradeData[rankId]) {
            return null;
        }

        var selectedUpgrade = window.StoreData.upgradeData[rankId];
        var basePrice = selectedUpgrade.price;
        var userCurrentPrice = window.StoreData.userCurrentRankPrice || 0;
        var userHighestPriority = window.StoreData.userHighestRankPriority || 0;

        // Only apply upgrade pricing if:
        // 1. User has a current rank (priority > 0)
        // 2. Selected rank is higher priority than current
        // 3. User is purchasing for themselves (checked in updateStoreUI)
        if (userHighestPriority > 0 && selectedUpgrade.rankPriority > userHighestPriority && userCurrentPrice > 0) {
            var upgradePrice = basePrice - userCurrentPrice;
            return Math.max(0, upgradePrice);
        }

        return basePrice;
    }

    /**
     * Makes tooltip message when hovering over the upgrade discount applied text.
     * Uses visitor username to decide whether to use "you" or recipient's name.
     * @param {number} savings - Amount saved by upgrade
     * @param {string} currentRecipient - Display name of current recipient
     * @returns {string} Tooltip text
     */
    function buildUpgradeTooltipMessage(savings, currentRecipient) {
        var whoPart = (currentRecipient === window.StoreData.visitorUsername) ? "you already own" : currentRecipient + " already owns";
        return "You're saving $" + savings.toFixed(2) + " because " + whoPart + " a rank.";
    }

    function updateStoreUI(rankId, skipValidation) {
        var selectedCard = document.querySelector("[data-rank-id=\"" + rankId + "\"]");
        var hasOwnedStatus = selectedCard && selectedCard.querySelector(".rank-status") &&
            selectedCard.querySelector(".rank-status").textContent.includes("Already Purchased");

        if (!skipValidation && window.StoreData && window.StoreData.upgradeData && window.StoreData.upgradeData[rankId]) {
            var verifiedUpgrade = window.StoreData.upgradeData[rankId];

            document.getElementById("selectedPackage-name").textContent = verifiedUpgrade.title;
            selectedRankName = verifiedUpgrade.title;

            var recipientElement = document.getElementById("selectedPackage-foruser");
            var isPurchasingForSelf = recipientElement && recipientElement.textContent === window.StoreData.visitorUsername;

            // Use StoreApp's recipient calculation if available, otherwise fall back to visitor's data
            var upgradePrice = null;
            if (window.StoreApp && typeof window.StoreApp.calculateUpgradePriceForRecipient === "function") {
                upgradePrice = window.StoreApp.calculateUpgradePriceForRecipient(rankId);
            } else if (isPurchasingForSelf) {
                upgradePrice = calculateUpgradePrice(rankId);
            }

            if (upgradePrice !== null && upgradePrice < verifiedUpgrade.price) {
                var priceDisplay = "<span class=\"original-price\" style=\"text-decoration: line-through; color: #999; margin-right: 8px;\">$" + verifiedUpgrade.price.toFixed(2) + "</span>";
                priceDisplay += "<span class=\"upgrade-price\" style=\"color: #4caf50; font-weight: bold;\">$" + upgradePrice.toFixed(2) + "</span>";

                document.getElementById("selectedPackage-price").innerHTML = priceDisplay;
            } else {
                document.getElementById("selectedPackage-price").textContent = "$" + verifiedUpgrade.price.toFixed(2);
            }
        } else if (!skipValidation) {
            var titleElement = document.getElementById(rankId + "-title");
            var priceElement = document.getElementById(rankId + "-price");

            document.getElementById("selectedPackage-name").textContent = titleElement ? titleElement.textContent : "Unknown";
            document.getElementById("selectedPackage-price").textContent = priceElement ? priceElement.textContent : "Unknown";
            selectedRankName = titleElement ? titleElement.textContent : "Unknown";
        }

        // Check if visitor has lower-tier rank
        var isLowerTierForVisitor = false;
        var recipientElement = document.getElementById("selectedPackage-foruser");
        if (window.StoreData && window.StoreData.upgradeData && window.StoreData.upgradeData[rankId] && recipientElement) {
            var currentRecipient = recipientElement.textContent;
            var selectedUpgrade = window.StoreData.upgradeData[rankId];
            var userHighestPriority = window.StoreData.userHighestRankPriority || 0;

            if (currentRecipient === window.StoreData.visitorUsername &&
                userHighestPriority > 0 &&
                selectedUpgrade.rankPriority <= userHighestPriority) {
                isLowerTierForVisitor = true;
            }
        }

        if ((hasOwnedStatus && recipientElement && recipientElement.textContent === window.StoreData.visitorUsername) || isLowerTierForVisitor) {
            document.getElementById("paypal-button").style.display = "none";
            document.getElementById("purchase-placeholder").style.display = "block";
        } else {
            document.getElementById("paypal-button").style.display = "block";
            document.getElementById("purchase-placeholder").style.display = "none";
        }

        document.getElementById("gift-action").style.display = "block";

        // Handle gift notice display
        if (recipientElement) {
            var currentRecipient = recipientElement.textContent;
            var showNotice = false;
            var noticeMessage = "";

            if (hasOwnedStatus && currentRecipient === window.StoreData.visitorUsername) {
                showNotice = true;
                noticeMessage = "<i class=\"fa fa-info-circle\"></i> You already own this rank. Use the gift button to purchase for another player.";
            } else if (isLowerTierForVisitor) {
                showNotice = true;
                noticeMessage = "<i class=\"fa fa-info-circle\"></i> You already own a higher-tier rank. Use the gift button to purchase for another player.";
            }

            var giftNotice = document.getElementById("gift-notice");
            if (showNotice) {
                if (!giftNotice) {
                    giftNotice = document.createElement("div");
                    giftNotice.id = "gift-notice";
                    giftNotice.className = "message message--notice";
                    recipientElement.parentNode.insertBefore(giftNotice, recipientElement.nextSibling);
                }
                giftNotice.innerHTML = noticeMessage;
                giftNotice.style.display = "block";
            } else {
                if (giftNotice) {
                    giftNotice.style.display = "none";
                }
            }

            var upgradeNotice = document.getElementById("upgrade-notice");
            var showUpgradeNotice = false;
            var upgradeMessage = "";

            if (window.StoreData.upgradeData && window.StoreData.upgradeData[rankId]) {
                var selectedUpgrade = window.StoreData.upgradeData[rankId];

                // Use StoreApp's recipient calculation if available
                var calcUpgradePrice = null;
                if (window.StoreApp && typeof window.StoreApp.calculateUpgradePriceForRecipient === "function") {
                    calcUpgradePrice = window.StoreApp.calculateUpgradePriceForRecipient(rankId);
                } else if (currentRecipient === window.StoreData.visitorUsername) {
                    calcUpgradePrice = calculateUpgradePrice(rankId);
                }

                if (calcUpgradePrice !== null && calcUpgradePrice < selectedUpgrade.price) {
                    var savings = selectedUpgrade.price - calcUpgradePrice;
                    showUpgradeNotice = true;

                    var tooltipMessage = "You're saving $" + savings.toFixed(2) + " because " +
                        (currentRecipient === window.StoreData.visitorUsername ? "you already own" : currentRecipient + " already owns") +
                        " a rank.";
                    upgradeMessage = "<i class=\"fa fa-arrow-up\"></i> <span class=\"upgrade-discount-text\" data-xf-init=\"tooltip\" title=\"" + tooltipMessage + "\">Upgrade Discount Applied!</span>";
                }
            }

            if (showUpgradeNotice) {
                if (!upgradeNotice) {
                    upgradeNotice = document.createElement("div");
                    upgradeNotice.id = "upgrade-notice";
                    upgradeNotice.style.marginTop = "10px";
                    upgradeNotice.style.color = "#4caf50";
                    var priceElement = document.getElementById("selectedPackage-price");
                    if (priceElement && priceElement.parentNode) {
                        priceElement.parentNode.insertBefore(upgradeNotice, priceElement.nextSibling);
                    }
                }
                upgradeNotice.innerHTML = upgradeMessage;
                upgradeNotice.style.display = "block";

                // Gotta call XenForo tooltip handler so tooltip appears when hovering over
                XF.activate(upgradeNotice);
            } else {
                if (upgradeNotice) {
                    upgradeNotice.style.display = "none";
                }
            }
        }

        var giftButton = document.getElementById("gift-button");
        if (giftButton) {
            giftButton.setAttribute("data-rankid", rankId);
        }

        updateResetButton();
        updateSelectedRankIcon(rankId);
        updateCardSelections(rankId);
    }

    function resetStoreUI() {
        selectedRank = 0;
        selectedRankName = "";

        document.getElementById("selectedPackage-name").textContent = "No rank selected";
        document.getElementById("selectedPackage-price").textContent = "$0.00";
        document.getElementById("paypal-button").style.display = "none";
        document.getElementById("purchase-placeholder").style.display = "block";
        document.getElementById("gift-action").style.display = "none";

        var giftNotice = document.getElementById("gift-notice");
        if (giftNotice) {
            giftNotice.style.display = "none";
        }

        var upgradeNotice = document.getElementById("upgrade-notice");
        if (upgradeNotice) {
            upgradeNotice.style.display = "none";
        }

        var resetButton = document.getElementById("reset-to-self-button");
        if (resetButton) {
            resetButton.style.display = "none";
        }

        document.getElementById("selectedRank-icon").style.display = "none";
        document.getElementById("selectedRank-placeholder").style.display = "block";

        clearAllSelections();
    }

    /**
     * Clears upgrade discount notice that shows under selected rank price.
     * Called when switching to gift mode since upgrade discounts don't apply to gifts.
     * @returns {void}
     */
    function clearUpgradeNotice() {
        var upgradeNotice = document.getElementById("upgrade-notice");
        if (upgradeNotice) {
            upgradeNotice.style.display = "none";
        }
    }

    /**
     * Iterates through all rank cards and removes selection/disabled classes so
     * they can be selected.
     * @returns {void}
     */
    function clearAllSelections() {
        var allCards = document.querySelectorAll(".rank-card");

        allCards.forEach(function (card) {
            card.classList.remove("rank-card-selected", "rank-card-disabled");
        });
    }

    /**
     * Iterates through all rank cards and sets selected card as active and
     * disables other cards.
     * @param {string|number} selectedRankId - rank ID of the currently selected rank card
     * @returns {void}
     */
    function updateCardSelections(selectedRankId) {
        var allCards = document.querySelectorAll(".rank-card");

        allCards.forEach(function (card) {
            var rankId = card.getAttribute("data-rank-id");

            if (rankId == selectedRankId) {
                card.classList.add("rank-card-selected");
                card.classList.remove("rank-card-disabled");
            } else {
                card.classList.add("rank-card-disabled");
                card.classList.remove("rank-card-selected");
            }
        });
    }

    /**
     * Updates selected rank icon in purchase summary sidebar by copying the
     * rank image from selected card and hiding placeholder in summary sidebar.
     * @param {string|number} rankId - rank ID to display the icon for
     * @returns {void}
     */
    function updateSelectedRankIcon(rankId) {
        var rankCard = document.querySelector("[data-rank-id=\"" + rankId + "\"]");

        if (rankCard) {
            var rankImage = rankCard.querySelector(".rank-image");
            var iconElement = document.getElementById("selectedRank-icon");
            var placeholderElement = document.getElementById("selectedRank-placeholder");

            if (rankImage && iconElement && placeholderElement) {
                iconElement.src = rankImage.src;
                iconElement.style.display = "block";
                placeholderElement.style.display = "none";
            }
        }
    }

    function updateResetButton() {
        if (!window.StoreData || !window.StoreData.isLoggedIn) {
            return;
        }

        var isGifting = StoreApp.selectedUser !== window.StoreData.visitorId;
        var resetButton = document.getElementById("reset-to-self-button");

        if (resetButton) {
            if (isGifting) {
                resetButton.style.display = "block";
            } else {
                resetButton.style.display = "none";
            }
        }
    }

    window.StoreApp = StoreApp;

    window.clearAllSelections = clearAllSelections;
    window.clearUpgradeNotice = clearUpgradeNotice;
    window.updateCardSelections = updateCardSelections;
    window.updateSelectedRankIcon = updateSelectedRankIcon;
    window.updateResetButton = updateResetButton;
    window.setStoreUser = function (userId, userName) {
        StoreApp.setStoreUser(userId, userName);
    };

    XF.Element.register("gmod-store-packageselect", "XF.StorePackageSelect");
    XF.Element.register("gmod-store-giftpackage", "XF.StoreGiftButton");

    document.addEventListener("DOMContentLoaded", function () {
        var nameElement = document.getElementById("selectedPackage-name");
        var priceElement = document.getElementById("selectedPackage-price");
        var paypalButton = document.getElementById("paypal-button");
        var placeholder = document.getElementById("purchase-placeholder");
        var iconElement = document.getElementById("selectedRank-icon");
        var placeholderIcon = document.getElementById("selectedRank-placeholder");
        var giftAction = document.getElementById("gift-action");

        if (nameElement) nameElement.textContent = "No rank selected";
        if (priceElement) priceElement.textContent = "$0.00";
        if (paypalButton) paypalButton.style.display = "none";
        if (placeholder) placeholder.style.display = "block";
        if (iconElement) iconElement.style.display = "none";
        if (placeholderIcon) placeholderIcon.style.display = "block";
        if (giftAction) giftAction.style.display = "none";

        // Clear all selections
        if (typeof clearAllSelections === "function") {
            clearAllSelections();
        }

        StoreApp.init();
    });
}
(XF, window, document);