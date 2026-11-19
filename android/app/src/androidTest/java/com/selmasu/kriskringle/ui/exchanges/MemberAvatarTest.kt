package com.selmasu.kriskringle.ui.exchanges

import android.graphics.Bitmap
import android.graphics.Color
import androidx.compose.material3.MaterialTheme
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.setValue
import androidx.compose.ui.test.*
import androidx.compose.ui.test.junit4.createComposeRule
import androidx.test.ext.junit.runners.AndroidJUnit4
import com.selmasu.kriskringle.data.KrisKringleMember
import java.io.ByteArrayOutputStream
import java.util.concurrent.CopyOnWriteArrayList
import java.util.concurrent.atomic.AtomicInteger
import org.junit.Assert.assertEquals
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith

/** Exercises photo loading through injected loaders; these tests never contact the server. */
@RunWith(AndroidJUnit4::class)
class MemberAvatarTest {
    @get:Rule val compose = createComposeRule()

    private fun member(pictureURL: String? = null, pictureVersion: String = "") = KrisKringleMember(
        id = 42, memberId = 7, groupId = 1, name = "Alex", email = null,
        pictureVersion = pictureVersion, pictureURL = pictureURL
    )

    private fun photo(): Bitmap = Bitmap.createBitmap(8, 8, Bitmap.Config.ARGB_8888).apply { eraseColor(Color.RED) }
    private fun jpeg(): ByteArray = ByteArrayOutputStream().use { output ->
        photo().also { image -> image.compress(Bitmap.CompressFormat.JPEG, 90, output); image.recycle() }
        output.toByteArray()
    }

    private fun awaitPhoto() {
        compose.waitUntil(5_000) {
            compose.onAllNodesWithContentDescription("Photo of Alex").fetchSemanticsNodes().isNotEmpty()
        }
        compose.onNodeWithContentDescription("Photo of Alex").assertIsDisplayed()
    }

    @Test fun suppliedProfilePhotoRendersWithoutNetworkOrRosterMetadata() {
        val requests = AtomicInteger()
        val bytes = jpeg()
        compose.setContent {
            MaterialTheme {
                MemberAvatar(11, member(), picture = bytes,
                    loadPicture = { _, _, _ -> requests.incrementAndGet(); error("Unexpected member-photo request") },
                    loadURL = { _, _ -> requests.incrementAndGet(); error("Unexpected URL request") })
            }
        }
        awaitPhoto()
        assertEquals(0, requests.get())
    }

    @Test fun uploadedPhotoLoadsWhenRosterHasNoPictureMetadata() {
        val requests = CopyOnWriteArrayList<Pair<Int, Int>>()
        val bytes = jpeg()
        compose.setContent {
            MaterialTheme {
                MemberAvatar(11, member(), loadPicture = { _, exchangeId, userId ->
                    requests += exchangeId to userId
                    bytes
                }, loadURL = { _, _ -> error("The authenticated image should be used.") })
            }
        }
        awaitPhoto()
        assertEquals(listOf(11 to 42), requests.toList())
    }

    @Test fun failedAuthenticatedPhotoFallsBackToPictureURL() {
        val urls = CopyOnWriteArrayList<String>()
        val fallback = photo()
        compose.setContent {
            MaterialTheme {
                MemberAvatar(11, member("https://example.test/avatar.jpg", "version-1"),
                    loadPicture = { _, _, _ -> throw IllegalStateException("Photo not found") },
                    loadURL = { _, url -> urls += url; fallback })
            }
        }
        awaitPhoto()
        assertEquals(listOf("https://example.test/avatar.jpg"), urls.toList())
    }

    @Test fun sameMemberReloadsWhenExchangeChanges() {
        val requests = CopyOnWriteArrayList<Int>()
        val bytes = jpeg()
        var exchangeId by mutableIntStateOf(11)
        compose.setContent {
            MaterialTheme {
                MemberAvatar(exchangeId, member(), loadPicture = { _, id, _ -> requests += id; bytes },
                    loadURL = { _, _ -> error("Unexpected URL load") })
            }
        }
        awaitPhoto()
        compose.runOnIdle { exchangeId = 12 }
        compose.waitUntil(5_000) { requests.size == 2 }
        awaitPhoto()
        assertEquals(listOf(11, 12), requests.toList())
    }

    @Test fun failedExpectedPhotoCanBeRetried() {
        val attempts = AtomicInteger()
        val bytes = jpeg()
        compose.setContent {
            MaterialTheme {
                MemberAvatar(11, member(pictureVersion = "version-1"), loadPicture = { _, _, _ ->
                    if (attempts.incrementAndGet() == 1) throw IllegalStateException("Temporary failure")
                    bytes
                }, loadURL = { _, _ -> error("Unexpected URL load") })
            }
        }
        compose.waitUntil(5_000) {
            compose.onAllNodesWithContentDescription("Retry photo for Alex").fetchSemanticsNodes().isNotEmpty()
        }
        compose.onNodeWithContentDescription("Retry photo for Alex").performClick()
        awaitPhoto()
        assertEquals(2, attempts.get())
    }
}
